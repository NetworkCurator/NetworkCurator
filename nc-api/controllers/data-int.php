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
        $params = $this->subsetArray($this->_params, ["file_name", "file_desc", "file_content"]);
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

        $this->dblock([NC_TABLE_FILES, NC_TABLE_NODES, NC_TABLE_LINKS,
            NC_TABLE_CLASSES, NC_TABLE_ANNOTEXT, NC_TABLE_ACTIVITY,
            NC_TABLE_ANNOTEXT." AS nodenameT", NC_TABLE_ANNOTEXT." AS classnameT",
            NC_TABLE_ANNOTEXT." AS linknameT"]);
        
        //echo "ID5 ";
        // store a record of the file in the db        
        $sql = "INSERT INTO " . NC_TABLE_FILES . "
                   (datetime, user_id, network_id, file_name, 
                   file_type, file_desc, file_size) VALUES 
                   (UTC_TIMESTAMP(), :user_id, :network_id, :file_name,
                   :file_type, :file_desc, :file_size)";
        $pp = array_merge(['network_id' => $this->_netid, 'user_id' => $this->_uid,
            'file_type' => 'json', 'file_size' => strlen($filestring)], $params);
        $this->qPE($sql, $pp);
        $fileid = $this->lID();
        //echo "ID6 ";
        // store the file on disk
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/W" . $this->_netid;
        $filepath = $networkdir . "/F" . $fileid . ".json";
        file_put_contents($filepath, $filestring);
        chmod($filepath, 0777);

        //echo "ID7 ";
        // log the upload
        $this->logActivity($this->_uname, $this->_netid, "uploaded data file", $params['file_name'], $params['file_desc']);
       // echo "ID8 ";
        // it will be useful to have access to the ontology                
        $nodeontology = $this->getNodeOntology();
        $linkontology = $this->getLinkOntology();
        //echo "ID9 ";
        $status = "";
        $this->setLogging(false);

        // import ontology, nodes, links
        $timer->recordTime("importSummary");
        if ($this->_uperm >= NC_PERM_CURATE) {
            $status .= $this->importSummary($filedata['network'][0], $params["file_name"]);
        }
        //echo "ID 20 ";
        $timer->recordTime("importOntology");
        if ($this->_uperm >= NC_PERM_CURATE && array_key_exists('ontology', $filedata)) {
            $status .= $this->importOntology($nodeontology, $linkontology, $filedata['ontology'], $params['file_name']);            
        }

        // avoid further costly work if not necessary
        if (!array_key_exists('nodes', $filedata) && array_key_exists('links', $filedata)) {
            return $status;
        }

        // re-get the node and link ontology after the adjustments
        $nodeontology = $this->getNodeOntology();
        $linkontology = $this->getLinkOntology();

        // drop certain indexes on the annotation table 
        try {
            $sql = "ALTER TABLE " . NC_TABLE_ANNOTEXT . " DROP INDEX root_id, DROP INDEX anno_type ";
            $this->q($sql);
        } catch (Exception $ex) {
            echo "could not drop indexes?\n";
        }

        //echo "ID 30 ";
        $timer->recordTime("importNodes");
        if (array_key_exists('nodes', $filedata)) {
            $status .= $this->importNodes($nodeontology, $filedata['nodes'], $params["file_name"]);
        }
        //echo "ID 40 ";
        $timer->recordTime("importLinks");
        if (array_key_exists('links', $filedata)) {
            //$status .= $this->importLinks($nodeontology, $linkontology, $filedata['links'], $params["file_name"]);
        }
        //echo "ID 50 ";
        // recreate indexes on annotation table
        $timer->recordTime("indexing");
        try {            
            $sql = "ALTER TABLE ".NC_TABLE_ANNOTEXT ." ADD INDEX root_id (network_id, root_id),
                ADD INDEX anno_type (network_id, anno_type)";
            $this->q($sql);            
        } catch (Exception $ex) {
            echo "error creating index: ".$ex->getMessage()."\n";
        }
        //echo "ID 60 ";
        $timer->recordTime("import end");

        echo $timer->showTimes();
        return "$status\n";
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
            'root_type' => NC_GRAPH,
            'parent_id' => $this->_netid, 'user_id' => $this->_uid];
        if (array_key_exists('title', $netdata)) {
            $nowinfo = $this->getAnnoInfo($this->_netid, $this->_netid, NC_GRAPH, NC_TITLE);
            $nowp = array_merge($corep, $nowinfo, ['anno_text' => $netdata['title'], 'anno_type' => NC_TITLE]);           
            $changecounter += ($this->updateAnnoText($nowp)) > 0;
        }
        if (array_key_exists('abstract', $netdata)) {
            $nowinfo = $this->getAnnoInfo($this->_netid, $this->_netid, NC_GRAPH, NC_ABSTRACT);
            $nowp = array_merge($corep, $nowinfo, ['anno_text' => $netdata['abstract'], 'anno_type' => NC_ABSTRACT]);
            $changecounter += ($this->updateAnnoText($nowp)) > 0;
        }
        if (array_key_exists('content', $netdata)) {
            $nowinfo = $this->getAnnoInfo($this->_netid, $this->_netid, NC_GRAPH, NC_CONTENT);
            $nowp = array_merge($corep, $nowinfo, ['anno_text' => $netdata['content'], 'anno_type' => NC_CONTENT]);
            $changecounter += ($this->updateAnnoText($nowp)) > 0;
        }
        if ($changecounter > 0) {
            $this->logActivity($this->_uname, $this->_netid, "applied annotation changes from file", $filename, $changecounter);
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
    private function equalOntoData($x, $y, $types = ['parent_name', 'connector', 'directional', 'class_status']) {
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
     * @param array $newdata
     * 
     * array with new ontology data
     * 
     * @param string $filename
     * 
     * used for reporting in the log only
     * 
     * @return type
     */
    private function importOntology($nodeonto, $linkonto, $newdata, $filename) {

        // consider node and link ontology types together
        $onto = array_merge($nodeonto, $linkonto);
     
        // track number of ontology operations and error messages
        $numadded = 0;
        $numskipped = 0;
        $numupdated = 0;
        $msgs = "";

        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
                $nowname = $newdata[$i]['name'];    
                //echo $nowname." ";
                try {
                    $nowresult = $this->processOneOntoEntry($newdata[$i], $onto);
                    if ($nowresult == "update") {
                        $numupdated++;
                    } else if ($nowresult == "add") {
                        $numadded++;
                    } else {
                        $numskipped++;
                    }
                } catch (Exception $ex) {
                    $numskipped++;
                    $msgs .= $nowname . ": " . $ex->getMessage() . "\n";
                }
            }
        }

        if ($numadded > 0) {
            $this->logActivity($this->_uid, $this->_netid, "added ontology classes from file", $filename, $numadded);
        }
        if ($numupdated > 0) {
            $this->logActivity($this->_uid, $this->_netid, "updated ontology classes from file", $filename, $numupdated);
        }

        return $msgs . " -- ontology: added $numadded / updated $numupdated / skipped $numskipped\n";
    }

    /**
     * 
     * @param array $newentry
     * @param array $ontology
     */
    private function processOneOntoEntry($newentry, $ontology) {

        // normalize the entry
        $classname = $newentry['name'];
        $newentry['class_name'] = $newentry['name'];
        if (!array_key_exists("connector", $newentry)) {
            $newentry['connector'] = 0;
        }
        if (!array_key_exists("directional", $newentry)) {
            $newentry['directional'] = 0;
        }
        if (array_key_exists("status", $newentry)) {
            $newentry['class_status'] = ((int) $newentry['class_status'] > 0);
        } else {
            $newentry['class_status'] = 1;
        }
        if (array_key_exists("title", $newentry)) {
            $newentry['class_title'] = $newentry['title'];
        } else {
            $newentry['class_title'] = $newentry['name'];
        }
        if (array_key_exists("parent", $newentry)) {
            $newentry['parent_name'] = $newentry['parent'];
        } else {
            $newentry['parent_name'] = '';
        }

        $updated = false;

        if (array_key_exists($classname, $onto)) {
            if ($newentry['class_status'] == 1) {
                // perhaps adjust the status
                if ($onto[$classname]['class_status'] == 0) {
                    // requires activation
                    // echo "activating " . $classname."\n";
                    $this->activateClassWork($classname);
                    // manually update the ontology here
                    $onto[$classname]['class_status'] = 1;
                    $updated = true;
                }
                // perhaps adjust other properties
                if (!$this->equalOntoData($newentry, $onto[$classname])) {
                    //echo "updating " . $nowdata['class_name']."\n"; 
                    $newentry['class_newname'] = $newentry['name'];

                    // call updateClassWork, but it needs: "class_name", "parent_name", 
                    // "connector", "directional", "class_newname", "class_status"                  
                    $this->updateClassWork($newentry);
                    $updated = true;
                }
            } else {
                // here new entry has 0 status
                if ($onto[$nowname]['class_status'] == 1) {
                    // requires de-activation
                    //echo "removing " . $nowdata['class_name']."\n";
                    $this->removeClassWork($classname);
                    $updated = true;
                }
            }
        } else {
            // class does not exists, so create from scratch            
            //echo "creating class " . $classname."\n";
            if ($newentry['class_status'] > 0) {
                $this->createNewClassWork($newentry);              
                return "add";
            } else {
                return "skip";
            }
        }

        if ($updated) {
            return "update";
        } else {
            return "skip";
        }
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