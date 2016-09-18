<?php

/**
 * Class handling requests for data import and export
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCData extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger    
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_netid;
    private $_uperm;

    /**
     * Constructor 
     * 
     * @param type $db
     * 
     * Connection to the NC database
     * 
     * @param type $params
     * 
     * array with parameters
     */
    public function __construct($db, $params) {

        $this->_db = $db;
        $this->_params = $params;

        // check for required parameters
        if (isset($params['network_name'])) {
            $this->_network = $this->_params['network_name'];
        } else {
            $this->_network = "";
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
        }

        // all functions will need to know the network id code
        $this->_netid = $this->getNetworkId($this->_network, true);

        // all functions will need user permission level
        $this->_uperm = $this->getUserPermissionsNetID($this->_netid, $this->_uid);
    }

    /**
     * 
     * @return boolean
     * 
     * @throws Exception
     */
    public function importData() {

        $tstart = microtime(true);

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["user_id", "file_name", "file_desc",
            "file_content"]);

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_EDIT) {
            throw new Exception("Insufficient permissions");
        }

        $filedata = json_decode($params['file_content'], true);
        $filestring = json_encode($filedata, JSON_PRETTY_PRINT);
        unset($params['file_content']);

        // check if the file data matches the requested network
        if (!array_key_exists('network', $filedata)) {
            throw new Exception('Input file must specify network');
        }
        if (!array_key_exists('name', $filedata['network'][0])) {
            throw new Exception('Input file must specify network name');
        }
        if ($this->_network != $filedata['network'][0]['name']) {
            throw new Exception('Input file does not match network');
        }

        // store the file on disk
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $this->_netid;
        $fileid = $this->makeRandomID(NC_TABLE_FILES, 'file_id', 'D', NC_ID_LEN);
        file_put_contents($networkdir . "/" . $fileid . ".json", $filestring);

        // store the file in the db        
        $sql = "INSERT INTO " . NC_TABLE_FILES . "
                   (datetime, file_id, user_id, network_id, file_name, 
                   file_type, file_desc, file_size) VALUES 
                   (UTC_TIMESTAMP(), :file_id, :user_id, :network_id, :file_name,
                   :file_type, :file_desc, :file_size)";
        $pp = array_merge(['file_id' => $fileid, 'network_id' => $this->_netid,
            'file_type' => 'json', 'file_size' => strlen($filestring)], $params);
        $stmt = prepexec($this->_db, $sql, $pp);

        // log the upload
        $this->logActivity($this->_uid, $this->_netid, "uploaded data file", $params['file_name'], $params['file_desc']);

        // drop certain indexes on the annotation table        
        try {
            $sql = "DROP INDEX root_id ON " . NC_TABLE_ANNOTEXT;
            $this->_db->prepare($sql)->execute();
        } catch (Exception $ex) {
            
        }

        // apply transformations in the file to the network description (title, etc)
        $changecounter = 0;
        if ($this->_uperm >= NC_PERM_CURATE) {
            $netdata = $filedata['network'][0];
            $corep = ['network_id' => $this->_netid, 'root_id' => $this->_netid,
                'parent_id' => $this->_netid, 'user_id' => $this->_uid];
            if (array_key_exists('title', $netdata)) {
                $nowid = $this->getAnnoId($this->_netid, $this->_netid, NC_TITLE);
                $nowp = array_merge($corep, ['anno_id' => $nowid,
                    'anno_text' => $netdata['title'], 'anno_level' => NC_TITLE]);
                $changecounter += $this->updateAnnoText($nowp);
            }
            if (array_key_exists('abstract', $netdata)) {
                $nowid = $this->getAnnoId($this->_netid, $this->_netid, NC_ABSTRACT);
                $nowp = array_merge($corep, ['anno_id' => $nowid,
                    'anno_text' => $netdata['abstract'], 'anno_level' => NC_ABSTRACT]);
                $changecounter += $this->updateAnnoText($nowp);
            }
            if (array_key_exists('content', $netdata)) {
                $nowid = $this->getAnnoId($this->_netid, $this->_netid, NC_CONTENT);
                $nowp = array_merge($corep, ['anno_id' => $nowid,
                    'anno_text' => $netdata['content'], 'anno_level' => NC_CONTENT]);
                $changecounter += $this->updateAnnoText($nowp);
            }
            if ($changecounter > 0) {
                $this->logActivity($this->_uid, $this->_netid, "applied annotation changes from file", $params['file_name'], $changecounter);
            }
        }

        // apply transformation to the network ontology (create new classes)
        $ontoadded = 0;
        $ontoskipped = 0;
        if ($this->_uperm >= NC_PERM_CURATE && array_key_exists('ontology', $filedata)) {

            $ontodata = $filedata['ontology'];

            include_once "NCOntology.php";
            $corep = ["network_name" => $this->_params['network_name'], "user_id" => $this->_uid,
                "parent_id" => '', "connector" => 0, "directional" => 0, "class_name" => ''];
            $NConto = new NCOntology($this->_db, $corep);
            $NConto->setLogging(false);

            // loop and create new classes for each entry in the file (if 
            for ($i = 0; $i < count($ontodata); $i++) {
                $nowp = $corep;
                if (array_key_exists('name', $ontodata[$i])) {
                    $nowp['class_name'] = $ontodata[$i]['name'];
                    if (array_key_exists('directional', $ontodata[$i])) {
                        $nowp['directional'] = $ontodata[$i]['directional'];
                    }
                    if (array_key_exists('connector', $ontodata[$i])) {
                        $nowp['connector'] = $ontodata[$i]['connector'];
                    }
                    try {
                        $NConto->resetParams($nowp);
                        $NConto->createNewClass();
                        $ontoadded++;
                    } catch (Exception $ex) {
                        $ontoskipped++;
                    }
                }
            }
        }


        // recreate indexes on annotation table
        try {
            $sql = "CREATE INDEX root_id ON " . NC_TABLE_ANNOTEXT . " (network_id, root_id)";
            $this->_db->prepare($sql)->execute();
        } catch (Exception $ex) {
            
        }

        $tend = microtime(true);
        return "(annos: $changecounter ontoadded: $ontoadded ontoskipped: $ontoskipped) time: " . ($tend - $tstart);
    }

}

?>