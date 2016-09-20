<?php

include_once "NCGraphs.php";
include_once "NCOntology.php";

/**
 * Class handling requests for data import and export
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCData extends NCGraphs {

    // db connection and array of parameters are inherited from NCLogger and NCGraphs
    // some variables extracted from $_params, for convenience   
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

        parent::__construct($db, $params);

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
        
        $timer = new NCTimer();
        $timer->recordTime("import start");
//echo "ID1 ";
        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["user_id", "file_name",
            "file_desc", "file_content"]);
//echo "ID2 ";
        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_EDIT) {
            throw new Exception("Insufficient permissions " . $this->_uperm);
        }
//echo "ID3 ";
        $filedata = json_decode($params['file_content'], true);
        $filestring = json_encode($filedata, JSON_PRETTY_PRINT);
        unset($params['file_content']);
//echo "ID4 ";
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
//echo "ID5 ";
        // store the file on disk
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $this->_netid;
        $fileid = $this->makeRandomID(NC_TABLE_FILES, 'file_id', 'D', NC_ID_LEN);
        $filepath = $networkdir . "/" . $fileid . ".json";
        file_put_contents($filepath, $filestring);
        chmod($filepath, 0777);
//echo "ID6 ";
        // store a record of the file in the db        
        $sql = "INSERT INTO " . NC_TABLE_FILES . "
                   (datetime, file_id, user_id, network_id, file_name, 
                   file_type, file_desc, file_size) VALUES 
                   (UTC_TIMESTAMP(), :file_id, :user_id, :network_id, :file_name,
                   :file_type, :file_desc, :file_size)";
        $pp = array_merge(['file_id' => $fileid, 'network_id' => $this->_netid,
            'file_type' => 'json', 'file_size' => strlen($filestring)], $params);
        $this->qPE($sql, $pp);
//echo "ID7 ";
        // log the upload
        $this->logActivity($this->_uid, $this->_netid, "uploaded data file", $params['file_name'], $params['file_desc']);
//echo "ID8 ";
        // drop certain indexes on the annotation table        
        try {
            $sql = "DROP INDEX root_id ON " . NC_TABLE_ANNOTEXT;
            $this->qPE($sql, []);
        } catch (Exception $ex) {
            echo "could not drop index root_id?\n";
        }
//echo "ID9 ";
        // it will be useful to have access to the ontology        
        $corep = ["network_name" => $this->_params['network_name'], "user_id" => $this->_uid,
            "parent_id" => '', "connector" => 0, "directional" => 0, "class_name" => ''];
        $NConto = new NCOntology($this->_db, $corep);
        $NConto->setLogging(false);
        $nodeontology = $NConto->getNodeOntology(false);
        $linkontology = $NConto->getLinkOntology(false);
//echo "ID9.5 ";

        $status = "";
        // import ontology, nodes, links
        $timer->recordTime("importSummary");
        if ($this->_uperm >= NC_PERM_CURATE) {
            $status .= $this->importSummary($filedata['network'][0], $params["file_name"]);
        }
//echo "ID 20 ";
        $timer->recordTime("importOntology");
        if ($this->_uperm >= NC_PERM_CURATE && array_key_exists('ontology', $filedata)) {
            $status .= $this->importOntology($NConto, $nodeontology, $linkontology, $filedata['ontology'], $params["file_name"]);
        }

        // re-get the node and link ontology after the adjustments
        $nodeontology = $NConto->getNodeOntology(false);
        $linkontology = $NConto->getLinkOntology(false);

//echo "ID 30 ";
        $timer->recordTime("importNodes");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('nodes', $filedata)) {
            $status .= $this->importNodes($nodeontology, $filedata['nodes'], $params["file_name"]);
        }
//echo "ID 40 ";
        $timer->recordTime("importLinks");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('links', $filedata)) {
            $status .= $this->importLinks($nodeontology, $linkontology, $filedata['links'], $params["file_name"]);
        }
//echo "ID 50 ";
        // recreate indexes on annotation table
        $timer->recordTime("indexing");
        try {
            $sql = "CREATE INDEX root_id ON " . NC_TABLE_ANNOTEXT . " (network_id, root_id)";
            $this->qPE($sql, []);
        } catch (Exception $ex) {
            echo "error creating index\n";
        }
//echo "ID 60 ";     
        $timer->recordTime("import end");        

        return "$status\n".$timer->showTimes();
    }

    /**
     * Helper function adjusts title, abstract, description of entire network
     * 
     * @param type $netdata
     * @param type $filename
     * @return type
     */
    private function importSummary($netdata, $filename) {

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
            $this->logActivity($this->_uid, $this->_netid, "applied annotation changes from file", $filename, $changecounter);
        }
        return " -- summary: changed: $changecounter\n";
    }

    /**
     * Helper function compares ontology classes x and y
     * 
     * @param type $x
     * @param type $y
     * @return boolean
     * 
     * true if x and y are equal along the specified types
     * false if they are different
     */
    private function equalOntoData($x, $y, $types = ['parent_id', 'connector', 'directional', 'class_status']) {
        foreach ($types as $nowtype) {
            if ($x[$nowtype] != $y[$nowtype]) {
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * @param NContology $NConto
     * controller class instance
     * @param array $nodeonto
     * 
     * array with existing node ontology
     * 
     * @param array $linkonto
     * 
     * array with existing link ontology
     * 
     * @param array $ontodata
     * 
     * array with new ontology data
     * 
     * @param string $filename
     * 
     * used for reporting in the log only
     * 
     * @return type
     */
    private function importOntology($NConto, $nodeonto, $linkonto, $ontodata, $filename) {

        // consider node and link ontology types together
        $onto = array_merge($nodeonto, $linkonto);

        // start a log with output messages
        $ans = "";

        // all procedures require: user_id, network_name
        // ontology procedures require        
        // "class_id", "parent_id", "connector", "directional", "class_name", "class_status"        
        $defaults = ["parent_id" => '', "connector" => 0, "directional" => 0,
            "class_name" => '', "class_id" => '', "class_status" => 1];

        // loop throw ontodata and make sure all entries have all fields        
        for ($i = 0; $i < count($ontodata); $i++) {
            if (array_key_exists('name', $ontodata[$i])) {
                $ontodata[$i]['class_name'] = $ontodata[$i]['name'];
                foreach ($defaults as $key => $value) {
                    if (!array_key_exists($key, $ontodata[$i])) {
                        $ontodata[$i][$key] = $value;
                    }
                }
            }
        }

        // track number of ontology operations
        $numadded = 0;
        $numskipped = 0;
        $numupdated = 0;

        for ($i = 0; $i < count($ontodata); $i++) {
            if (array_key_exists('name', $ontodata[$i])) {
                $nowdata = $ontodata[$i];
                $nowname = $nowdata['name'];
                $NConto->resetParams($nowdata);

                try {
                    if (array_key_exists($nowname, $onto)) {
                        $NConto->_params['class_id'] = $onto[$nowname]['class_id'];
                        if ($nowdata['class_status'] == 1) {
                            if ($onto[$nowname]['class_status'] == 1) {
                                // requires update, or nothing
                                if (!$this->equalOntoData($nowdata, $onto[$nowname])) {
                                    //echo "updating " . $nowdata['class_name']."\n";                                                                        
                                    $NConto->updateClass();
                                    $numupdated++;
                                } else {
                                    //echo "skipping equal\n";
                                    $numskipped++;
                                }
                            } else {
                                // requires activation
                                //echo "activating " . $nowdata['class_name']."\n";
                                $NConto->activateClass();
                                $numupdated++;
                            }
                        } else {
                            if ($onto[$nowname]['class_status'] == 1) {
                                // requires de-activation
                                //echo "removing " . $nowdata['class_name']."\n";
                                $NConto->removeClass();
                                $numupdated++;
                            } else {
                                // no updates on inactive components
                                //echo "skipping inactive\n";
                                $numskipped++;
                            }
                        }
                    } else {
                        // create this new ontology
                        //echo "creating class " . $nowdata['class_name']."\n";
                        $NConto->createNewClass();
                        $numadded++;
                    }
                } catch (Exception $ex) {
                    //print_r($NConto->_params);
                    $ans .= $ex->getMessage() . "\n";
                }
            }
        }

        if ($numadded > 0) {
            $this->logActivity($this->_uid, $this->_netid, "added ontology classes from file", $filename, $numadded);
        }
        if ($numupdated > 0) {
            $this->logActivity($this->_uid, $this->_netid, "updated ontology classes from file", $filename, $numupdated);
        }


        return $ans . " -- ontology: added $numadded / updated $numupdated / skipped $numskipped\n";
    }

    /**
     * Helper function processes adjustments for nodes
     * 
     * @param NCGraphs $NCgraph
     * @param array $nodeonto
     * @param array $nodedata
     * @param string $filename
     * 
     */
    private function importNodes($nodeonto, $nodedata, $filename) {
               
        // start a log with output messages
        $ans = "";

        // all nodes require a name, class_name, title, status
        $defaults = ["node_name" => '', "class" => '',
            "title" => '', "abstract" => '', "content" => '',
            "class_id" => '', "class_status" => 1];

        // loop throw ontodata and make sure all entries have all fields        
        for ($i = 0; $i < count($nodedata); $i++) {
            if (array_key_exists('name', $nodedata[$i])) {
                $nodedata[$i]['node_name'] = $nodedata[$i]['name'];
                foreach ($defaults as $key => $value) {
                    if (!array_key_exists($key, $nodedata[$i])) {
                        $nodedata[$i][$key] = $value;
                    }
                }
            }
        }

        // collect data from all nodes
        $allnodes = $this->getAllNodes(true);
       
        $numadded = 0;
        $numupdated = 0;
        $numskipped = 0;

        for ($i = 0; $i < count($nodedata); $i++) {
            if (array_key_exists('name', $nodedata[$i])) {
                $nowdata = $nodedata[$i];
                $nowname = $nowdata['name'];

                if (array_key_exists($nowname, $allnodes)) {
                    
                } else {
                    // insert the node? Start optimistic, skip if there are problems
                    $insertok = true;
                    $nowclass = $nowdata['class'];
                    if (array_key_exists($nowclass, $nodeonto)) {
                        if ($nodeonto[$nowclass]['class_status'] != 1) {
                            $insertok = false;
                        }
                        $nowclassid = $nodeonto[$nowclass]['class_id'];
                    } else {
                        $insertok = false;
                    }
                    if (strlen($nowdata['title']) < 2) {
                        $insertok = false;
                    }
                    //echo "considering: $nowname $nowclass $nowclassid $insertok \n";
                    if ($insertok) {
                        //echo "adding\n";
                        $this->insertNode($nowname, $nowclassid, $nowdata['title'], $nowdata['abstract'], $nowdata['content']);
                        $numadded++;
                    } else {
                        $numskipped++;
                    }
                }
            }
        }

        if ($numadded > 0) {
            $this->logActivity($this->_uid, $this->_netid, "added nodes from file", $filename, $numadded);
        }
        if ($numupdated > 0) {
            $this->logActivity($this->_uid, $this->_netid, "updated nodes from file", $filename, $numupdated);
        }

        return $ans . " -- nodes: added $numadded / updated $numupdated / skipped $numskipped\n";
    }

    /**
     * Helper function processes updates on links
     * 
     * 
     * @param type $NCgraph
     * @param type $nodeonto
     * @param type $linkonto
     * @param type $linkdata
     * @param type $filename
     * @return string
     */
    private function importLinks($nodeonto, $linkonto, $linkdata, $filename) {
        return "links: todo\n";
    }

}

?>