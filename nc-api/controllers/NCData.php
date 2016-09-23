<?php

include_once "NCGraphs.php";
include_once "NCOntology.php";
include_once "../helpers/NCTimer.php";

/**
 * Class handling requests for data import and export
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCData extends NCGraphs {

    // sets of nodes and links that should be inserted in batch
    private $_nodeset = [];
    private $_linkset = [];

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
        //echo "ID5 ";

        $this->dblock([NC_TABLE_FILES, NC_TABLE_NODES, NC_TABLE_LINKS,
            NC_TABLE_CLASSES, NC_TABLE_ANNOTEXT, NC_TABLE_ACTIVITY,
            NC_TABLE_ANNOTEXT . " AS nodenameT", NC_TABLE_ANNOTEXT . " AS classnameT",
            NC_TABLE_ANNOTEXT . " AS linknameT"]);

        // store the file on disk
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $this->_netid;
        $fileid = $this->makeRandomID(NC_TABLE_FILES, 'file_id', NC_PREFIX_FILE, NC_ID_LEN);
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
        $pp = array_merge(['user_id' => $this->_uid, 'file_id' => $fileid, 'network_id' => $this->_netid,
            'file_type' => 'json', 'file_size' => strlen($filestring)], $params);
        $this->qPE($sql, $pp);
        //echo "ID7 ";
        // log the upload
        $this->logActivity($this->_uid, $this->_netid, "uploaded data file", $params['file_name'], $params['file_desc']);
//echo "ID8 ";
        // drop certain indexes on the annotation table        

        //echo "ID9 ";
        // it will be useful to have access to the ontology in full detail        
        $nodeontology = $this->getNodeOntology(false, true);        
        $linkontology = $this->getLinkOntology(false, true);
        //echo "ID9.5 ";

        // NOTE: here tried to drop indexes before doing the adding work
        // However, overall the performance seemed better when the indexes were there
        // This is because most "insert" operations are now in batch.
        // Also, most "insert" operations require a previous round of "SELECT" which benefit 
        // a lot from the indexes
        //$timer->recordTime("dropINDEX");
        //try {
        //    $sql = "ALTER TABLE " . NC_TABLE_ANNOTEXT . " DROP INDEX root_id, DROP INDEX anno_type ";            
        //    $this->q($sql);
        //} catch (Exception $ex) {
        //    echo "could not drop indexes: " . $ex->getMessage() . "\n";
        //}
        
        //echo "ID10 ";
        $status = "";
        // import ontology, nodes, links
        $timer->recordTime("importSummary");
        if ($this->_uperm >= NC_PERM_CURATE) {
            $status .= $this->importSummary($filedata['network'][0], $params["file_name"]);
        }
        //echo "ID 20 ";
        $timer->recordTime("importOntology");
        if ($this->_uperm >= NC_PERM_CURATE && array_key_exists('ontology', $filedata)) {
            $status .= $this->importOntology($nodeontology, $linkontology, $filedata['ontology'], $params["file_name"]);
        }

        // re-get the node and link ontology after the adjustments. This time short format
        $nodeontology = $this->getNodeOntology(false, false);
        $linkontology = $this->getLinkOntology(false, false);        

        //echo "ID 30 ";
        $timer->recordTime("importNodes");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('nodes', $filedata)) {
            //echo "ID 31";
            $status .= $this->importNodes($nodeontology, $filedata['nodes'], $params["file_name"]);
        }
//echo "ID 40 ";
        $timer->recordTime("importLinks");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('links', $filedata)) {
            $status .= $this->importLinks($nodeontology, $linkontology, $filedata['links'], $params["file_name"]);
        }
                
        // NOTE: required if earlier DROP INDEX
        // $timer->recordTime("indexing");
        //try {
        //    $sql = "ALTER TABLE " . NC_TABLE_ANNOTEXT . " ADD INDEX root_id (network_id, root_id),
        //        ADD INDEX anno_type (network_id, anno_type)";
        //    $this->q($sql);
        //} catch (Exception $ex) {
        //    echo "error creating index: " . $ex->getMessage();
        //}

        $this->dbunlock();
        //echo "ID 60 ";
        $timer->recordTime("import end");
        echo $timer->showTimes();
//echo "importData end\n";
        return "$status\n". $timer->showTimes();
    }

    /**
     * Helper function adjusts title, abstract, description of entire network
     * 
     * @param type $netdata
     * @param type $filename
     * @return type
     */
    private function importSummary($netdata, $filename) {

        // first get the existing summary information
        $sql = "SELECT datetime, owner_id, anno_text, anno_id, anno_type,
                    network_id, root_id, parent_id FROM " . NC_TABLE_ANNOTEXT . "
            WHERE network_id = ? AND anno_type <= " . NC_CONTENT . " 
                AND root_id = ? AND parent_id = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $this->_netid, $this->_netid]);

        // copy the results into a local array
        $info = ['name' => [], 'title' => [], 'abstract' => [], 'content' => []];
        while ($row = $stmt->fetch()) {
            $row['user_id'] = $this->_uid;
            if ($row['anno_type'] == NC_TITLE) {
                $info['title'] = $row;
            } else if ($row['anno_type'] == NC_ABSTRACT) {
                $info['abstract'] = $row;
            } else if ($row['anno_type'] == NC_CONTENT) {
                $info['content'] = $row;
            }
        }

        // for each defined element, check if it needs updating, and update
        $changecounter = 0;
        foreach (['title', 'abstract', 'content'] as $type) {
            if (array_key_exists($type, $netdata)) {
                if ($netdata[$type] != $info[$type]['anno_text']) {
                    $this->updateAnnoText($info[$type]);
                    $changecounter++;
                }
            }
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
    private function importOntology($nodeonto, $linkonto, $newdata, $filename) {

        // consider node and link ontology types together
        $ontos = array_merge($nodeonto, $linkonto);
        // update the ontology by replacing parent_ids with parent_name
        $ontodict = $this->getOntologyDictionary();
        foreach (array_keys($ontos) as $key) {
            $nowpid = $ontos[$key]['parent_id'];
            if ($nowpid != '') {
                $ontos[$key]['parent_name'] = $ontodict[$nowpid];
            } else {
                $ontos[$key]['parent_name'] = '';
            }
        }

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
                    $nowresult = $this->processOneOntoEntry($newdata[$i], $ontos);
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
     * @param type $newentry
     * @param type $ontology
     */
    private function processOneOntoEntry($newentry, $ontology) {

        // normalize the entry 
        // i.e. change from fields for user-perspective ("parent") to 
        // fields for developer-perspective ("parent_id", "parent_name")
        $classname = $newentry['name'];
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
        foreach (['name', 'title', 'abstract', 'content'] as $type) {
            if (array_key_exists($type, $newentry)) {
                $newentry['class_' . $type] = $newentry[$type];
            }
        }
        if (array_key_exists("parent", $newentry)) {
            $newentry['parent_name'] = $newentry['parent'];
        } else {
            $newentry['parent_name'] = '';
        }

        $updated = false;

        if (array_key_exists($classname, $ontology)) {
            if ($newentry['class_status'] == 1) {
                // perhaps adjust the status
                if ($ontology[$classname]['class_status'] == 0) {
                    // requires activation
                    // echo "activating " . $classname."\n";
                    $this->activateClassWork($classname);
                    // manually update the ontology here
                    $ontology[$classname]['class_status'] = 1;
                    $updated = true;
                }
                // perhaps adjust other class structure properties
                if (!$this->equalOntoData($newentry, $ontology[$classname])) {
                    //echo "updating " . $nowdata['class_name']."\n"; 
                    $newentry['target_name'] = $newentry['name'];
                    $this->updateClassWork($newentry);
                    $updated = true;
                }
                // perhaps adjust title abstract content
                foreach (['class_title', 'class_abstract', 'class_content'] as $type) {
                    if (array_key_exists($type, $newentry)) {
                        if ($newentry[$type] != $ontology[$classname][$type]) {
                            throw new Exception("TODO: implement updates on title, abstract, content");
                            //$this->updateAnnoText($info[$type]);
                            //$changecounter++;
                        }
                    }
                }
            } else {
                // here new entry has 0 status
                if ($ontology[$nowname]['class_status'] == 1) {
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
            "title" => '', "abstract" => 'empty', "content" => 'empty',
            "class_status" => 1];

        // loop throw ontodata and make sure all entries have all fields        
        for ($i = 0; $i < count($nodedata); $i++) {
            if (array_key_exists('name', $nodedata[$i])) {
                $nodedata[$i]['node_name'] = $nodedata[$i]['name'];
                foreach ($defaults as $key => $value) {
                    if (!array_key_exists($key, $nodedata[$i])) {
                        $nodedata[$i][$key] = $value;
                    }
                }
                if ($nodedata[$i]['title']=='') {
                    $nodedata[$i]['title'] = $nodedata[$i]['name'];
                }
            }
        }

        // collect data from all nodes
        $allnodes = $this->getAllNodes(true);
        
        //echo "nodeont: ";
        //print_r($nodeonto);
        //echo "allnodes: ";
        //print_r($allnodes);
                
        $numadded = 0;
        $numupdated = 0;
        $numskipped = 0;

        for ($i = 0; $i < count($nodedata); $i++) {
            //echo $i."\n";
            if (array_key_exists('name', $nodedata[$i])) {
                $nowdata = $nodedata[$i];                
                $nowname = $nowdata['name'];
                //echo "  ".$nowname."\n";
                if (array_key_exists($nowname, $allnodes)) {
                    // to do
                } else {
                    // insert the node? Start optimistic, skip if there are problems
                    $insertok = true;
                    $nowclass = $nowdata['class'];
                    //echo "    $nowclass \n";
                    if (array_key_exists($nowclass, $nodeonto)) {
                        if ($nodeonto[$nowclass]['status'] != 1) {
                            $insertok = false;
                        }
                        $nowclassid = $nodeonto[$nowclass]['class_id'];
                        //echo "got mapping: $nowclass --> $nowclassid \n";
                    } else {
                        $insertok = false;
                    }
                    if (strlen($nowdata['title']) < 2) {
                        $insertok = false;
                    }
                    //echo "considering: $nowname $nowclass $nowclassid $insertok \n";
                    if ($insertok) {
                        //echo "adding\n";
                        $toadd  = $this->subsetArray($nodedata[$i], ['name', 'title','abstract', 'content']);
                        $toadd['class_id'] = $nowclassid;                        
                        $this->_nodeset[] = $toadd;
                        //$this->insertNode($nowname, $nowclassid, $nowdata['title'], $nowdata['abstract'], $nowdata['content']);
                        $numadded++;
                    } else {
                        $numskipped++;
                    }
                }
            }
        }


        $n = count($this->_nodeset);
        //echo "nodeset has $n \n";
        //print_r($this->_nodeset);
        
        if ($n > 0) {
            $this->batchInsertNodes($this->_nodeset);
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