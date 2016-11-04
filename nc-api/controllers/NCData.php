<?php

include_once "NCGraphs.php";
include_once "NCOntology.php";
include_once dirname(__FILE__)."/../helpers/NCTimer.php";

/**
 * Class handling requests for data import and export
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCData extends NCGraphs {

    // for batch insertions and updates, restrict number of items in the set
    private $_atatime = 5000;

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

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["file_name", "file_desc", "data"]);

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_EDIT) {
            throw new Exception("Insufficient permissions " . $this->_uperm);
        }
        
        $filedata = json_decode($params['data'], true);                
        unset($params['data']);

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

        // save the data onto disk
        $this->importJSON($filedata, $params);
        
        $this->dblock([NC_TABLE_FILES, NC_TABLE_NODES, NC_TABLE_LINKS,
            NC_TABLE_CLASSES, NC_TABLE_ANNOTEXT, NC_TABLE_ACTIVITY,
            NC_TABLE_ANNOTEXT . " AS nodenameT", NC_TABLE_ANNOTEXT . " AS classnameT",
            NC_TABLE_ANNOTEXT . " AS linknameT"]);

        //echo "I 1 ";
        // it will be useful to have access to the ontology in full detail        
        $nodeontology = $this->getNodeOntology(false, true);
        $linkontology = $this->getLinkOntology(false, true);
        //echo "I 2 ";
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

        $status = "";
        // import ontology, nodes, links
        $timer->recordTime("importSummary");
        if ($this->_uperm >= NC_PERM_CURATE) {
            $status .= $this->importSummary($filedata['network'][0], $params["file_name"]);
        }
        //echo "I 3 ";
        $timer->recordTime("importOntology");
        if ($this->_uperm >= NC_PERM_CURATE && array_key_exists('ontology', $filedata)) {
            $status .= $this->importOntology($nodeontology, $linkontology, $filedata['ontology'], $params["file_name"]);
        }
        //echo "I 4 ";
        // re-get the node and link ontology after the adjustments. This time short format
        $nodedict = array_flip($this->getOntologyDictionary("node"));
        $linkdict = array_flip($this->getOntologyDictionary("link"));
        //echo "I 5 ";
        $timer->recordTime("importNodes");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('nodes', $filedata)) {
            //echo "I 6a ";
            $status .= $this->importNodes($nodedict, $filedata['nodes'], $params["file_name"]);
            //echo "I 6z ";
        }
        $timer->recordTime("importLinks");
        if ($this->_uperm >= NC_PERM_EDIT && array_key_exists('links', $filedata)) {
            $status .= $this->importLinks($linkdict, $filedata['links'], $params["file_name"]);
        }
        $timer->recordTime("unlock");

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
        $timer->recordTime("import end");

        return "$status\n" . $timer->showTimes();
    }

    /**
     * Helper function takes data as text and saves the information onto disk
     * 
     * @param $filedata php object with the file contents
     * @param $params controller parameters passed on from importData
     * 
     */
    private function importJSON($filedata, $params) {
        
        $filestring = json_encode($filedata, JSON_PRETTY_PRINT);
        $this->dblock([NC_TABLE_FILES, NC_TABLE_ACTIVITY]);

        // store the file on disk
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $this->_netid;
        $fileid = $this->makeRandomID(NC_TABLE_FILES, 'file_id', NC_PREFIX_FILE, NC_ID_LEN);
        $filepath = $networkdir . "/" . $fileid . ".json";
        file_put_contents($filepath, $filestring);
        chmod($filepath, 0777);

        // store a record of the file in the db        
        $sql = "INSERT INTO " . NC_TABLE_FILES . "
                   (datetime, file_id, user_id, network_id, file_name, 
                   file_type, file_desc, file_size) VALUES 
                   (UTC_TIMESTAMP(), :file_id, :user_id, :network_id, :file_name,
                   :file_type, :file_desc, :file_size)";
        $pp = array_merge(['user_id' => $this->_uid, 'file_id' => $fileid, 'network_id' => $this->_netid,
            'file_type' => 'json', 'file_size' => strlen($filestring)], $params);
        $this->qPE($sql, $pp);

        // log the upload
        $this->logActivity($this->_uid, $this->_netid, "uploaded data file", $params['file_name'], $params['file_desc']);
        $this->dbunlock();
        
    }
    
    /**
     * Helper function adjusts title, abstract, description of entire network
     * 
     * @param array $netdata
     * 
     * simple array with name, title, abstract, content
     * 
     * @param string $filename
     * 
     * name of file being processed (used only to log events)
     * 
     * @return string
     * 
     * a short summary of the applied changes
     */
    private function importSummary($netdata, $filename) {

        // prepare the netdata into a format understood by batchCheckUpdateAnno
        $batchcheck = [$this->_netid => $netdata];
        $changecounter = $this->batchCheckUpdateAnno($this->_netid, $batchcheck);

        // log activity if anything happened
        if ($changecounter > 0) {
            $this->logActivity($this->_uid, $this->_netid, "applied annotation changes from file", $filename, $changecounter);
        }

        return " -- summary: changed: $changecounter\n";
    }

    /**
     * Helper function compares two arrays, but only on a subset of the elements
     * 
     * @param array $x
     * @param array $y
     * @return boolean
     * 
     * true if x and y are equal along the specified types
     * false if they are different
     */
    private function equalSubArray($x, $y, $types) {
        foreach ($types as $nowtype) {
            if ($x[$nowtype] != $y[$nowtype]) {
                return false;
            }
        }
        return true;
    }

    /**
     * invoked from main import routine. Handles high-level import of ontologies.
     * Starts with an array of newdata (all ontology definitions in an import file)
     * 
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

        // make sure all entry contain at least some default values for all required parameters        
        $defaults = ["title" => '', "abstract" => '', "content" => '', "class" => '',
            "defs" => '', "directional" => 0, "connector" => 0, "parent" => '', "status" => 1];

        for ($i = 0; $i < count($newdata); $i++) {
            foreach ($defaults as $key => $value) {
                if (!array_key_exists($key, $newdata[$i])) {
                    $newdata[$i][$key] = $value;
                }
            }
        }

        // track number of ontology operations and error messages
        $numadded = 0;
        $numskipped = 0;
        $numupdated = 0;
        $msgs = "";

        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
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
                    $msgs .= $newdata[$i]['name'] . ": " . $ex->getMessage() . "\n";
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
     * processes one ontology definition at a time.
     * 
     * 
     * @param type $newentry
     * @param type $ontology
     */
    private function processOneOntoEntry($newentry, $ontology) {

        // process the various scenarios: update/insert/activate/remove
        $classname = $newentry['name'];
        $updated = false;
        $activated = false;

        if (!array_key_exists($classname, $ontology)) {
            // class does not exists, so create from scratch                        
            if ($newentry['status'] > 0) {
                $this->createNewClassWork($newentry);
                return "add";
            } else {
                return "skip";
            }
        }

        // if reached here, the class already exists. Perhaps adjust.
        if ($newentry['status'] == 1) {
            // perhaps adjust the status
            if ($ontology[$classname]['status'] == 0) {
                $this->activateClassWork($classname);
                $activated = true;
                $updated = true;
            }
            // perhaps adjust other class structure properties
            if (!$this->equalSubArray($newentry, $ontology[$classname], ['parent',
                        'connector', 'directional'])) {
                $newentry['target'] = $newentry['name'];
                $this->updateClassWork($newentry);
                $updated = true;
            }
            // perhaps adjust title abstract content
            $batchupdate = [];
            foreach (['title', 'abstract', 'content', 'defs'] as $type) {
                if (array_key_exists($type, $newentry)) {
                    if ($newentry[$type] != '' && $newentry[$type] != $ontology[$classname][$type]) {
                        // prepare a set of data for updates
                        $oldentry = $ontology[$classname];
                        $classid = $ontology[$classname]['class_id'];
                        $pp = ['network_id' => $this->_netid,
                            'datetime' => $oldentry[$type . '_datetime'],
                            'owner_id' => $oldentry[$type . "_owner_id"],
                            'anno_id' => $oldentry[$type . '_anno_id'],
                            'root_id' => $classid, 'parent_id' => $classid,
                            'anno_text' => $newentry[$type],
                            'anno_type' => $this->_annotypeslong[$type]];
                        $batchupdate[] = $pp;
                        $updated = true;
                    }
                }
            }
            $this->batchUpdateAnno($batchupdate);
        } else {
            // here new entry has 0 status
            if ($ontology[$classname]['status'] == 1) {
                // requires de-activation             
                $this->removeClassWork($classname);
                $updated = true;
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
     * @param array $nodedict
     * 
     * node dictionary, i.e. map from node names to node ids
     * 
     * @param array $newdata
     * 
     * array with new/updated node definitions
     * 
     * @param string $filename
     * 
     */
    private function importNodes($nodedict, $newdata, $filename) {

        // start a log with output messages
        $msgs = "";
                
        // all nodes require a name, class_name, title, status
        $defaults = ["name" => '', "title" => '', "abstract" => '', "content" => '',
            "class" => '', "status" => 1];

        // loop throw the new data and make sure all entries have all fields    
        $numskipped = 0;
        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
                $nowname = $newdata[$i]['name'];
                // write-in all the required fields
                foreach ($defaults as $key => $value) {
                    if (!array_key_exists($key, $newdata[$i])) {
                        $newdata[$i][$key] = $value;
                    }
                }
                // check if the specified classes exist
                $nowclass = $newdata[$i]['class'];
                if (array_key_exists($nowclass, $nodedict)) {
                    $newdata[$i]['class_id'] = $nodedict[$nowclass];
                } else {
                    $msgs .= "Skipping node $nowname because class '$nowclass' is undefined\n";
                    unset($newdata[$i]['name']);
                    $numskipped++;
                }
            } else {
                $numskipped++;
            }
        }

        // collect data from all existing nodes
        $allnodes = $this->getAllNodes(true);

        // split the new data into items to insert/update        
        $newset = [];
        $updateset = [];
        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
                // move the node either to the update or the create batch (will be processed below)
                $nowname = $newdata[$i]['name'];
                if (array_key_exists($nowname, $allnodes)) {
                    $updateset[$allnodes[$nowname]['id']] = &$newdata[$i];
                } else {
                    $newset[] = &$newdata[$i];
                }
            }
        }
        unset($allnodes);

        // batch insert and update
        if (count($newset) > 0) {
            //echo "inserting new nodes ".count($newset)."! <br/>";
            for ($i = 0; $i < count($newset); $i+=$this->_atatime) {
                $tempset = array_slice($newset, $i, $this->_atatime);
                $this->batchInsertNodes($tempset);
            }
            $this->logActivity($this->_uid, $this->_netid, "added nodes from file", $filename, count($newset));
        }
        $updatecount = 0;
        if (count($updateset) > 0) {
            //echo "updating nodes ".count($updateset)."! <br/>";
            for ($i = 0; $i < count($newset); $i+=$this->_atatime) {
                $tempset = array_slice($updateset, $i, $this->_atatime);
                $updatecount += $this->batchCheckUpdateAnno($this->_netid, $tempset);
            }
            $this->logActivity($this->_uid, $this->_netid, "updated nodes from file", $filename, $updatecount);
        }

        return $msgs . " -- nodes: added " . count($newset) . " / updated " . $updatecount . " / skipped $numskipped\n";
    }

    /**
     * Helper function processes updates on links
     * 
     * 
     * 
     * @param type $nodedict
     *
     * node ontology dictionary, i.e. map from node names to node ids  
     * 
     * @param type $linkdict
     * 
     * link ontology dictionary, i.e. map from node names to node ids  
     * 
     * @param type $newdata
     * 
     * definitions of new/updated links
     * 
     * @param string $filename
     * 
     * used in log message
     * 
     * @return string
     */
    private function importLinks($linkdict, $newdata, $filename) {

        // start a log with output messages
        $msgs = "";

        // all nodes require a name, class_name, title, status
        $defaults = ["name" => '', "title" => '', "abstract" => '', "content" => '',
            "class" => '', 'source' => '', 'target' => '', "status" => 1];

        // get all the nodes and make a node dictionary (vertices, not ontology)    
        $allnodes = $this->getAllNodes(true);
        $vdict = [];
        foreach ($allnodes as $key => $val) {
            $vdict[$key] = $val['id'];
        }

        // loop throw the new data and make sure all entries have all fields    
        $numskipped = 0;
        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
                $nowname = $newdata[$i]['name'];
                // write-in all the required fields
                foreach ($defaults as $key => $value) {
                    if (!array_key_exists($key, $newdata[$i])) {
                        $newdata[$i][$key] = $value;
                    }
                }
                // check the specified link class is defined
                $nowclass = $newdata[$i]['class'];
                if (array_key_exists($nowclass, $linkdict)) {
                    $newdata[$i]['class_id'] = $linkdict[$nowclass];
                } else {
                    $msgs .= "Skipping link $nowname because class '$nowclass' is undefined\n";
                    unset($newdata[$i]['name']);
                }
                // check the source/target are valid
                $nowsource = $newdata[$i]['source'];
                $nowtarget = $newdata[$i]['target'];
                if (!array_key_exists($nowsource, $vdict) ||
                        !array_key_exists($nowtarget, $vdict)) {
                    $msgs .= "Skipping link $nowname because source or target do not exist\n";
                    unset($newdata[$i]['name']);
                } else {
                    $newdata[$i]['source_id'] = $vdict[$nowsource];
                    $newdata[$i]['target_id'] = $vdict[$nowtarget];
                }

                // after all this, check the link is still ok
                if (!array_key_exists('name', $newdata[$i])) {
                    $numskipped++;
                }
            } else {
                $numskipped++;
            }
        }
        // save a little memory by unsetting node information (no longer needed below)
        unset($allnodes, $vdict);

        // fetch existing links (required to check if defined link in newdata is new or not)
        $alllinks = $this->getAllLinks(true);

        // split the new data into items to insert/update
        $newset = [];
        $updateset = [];
        for ($i = 0; $i < count($newdata); $i++) {
            if (array_key_exists('name', $newdata[$i])) {
                // move the link either to the update or the create batch (will be processed below)
                $nowname = $newdata[$i]['name'];
                if (array_key_exists($nowname, $alllinks)) {
                    $updateset[$alllinks[$nowname]['id']] = &$newdata[$i];
                } else {
                    $newset[] = &$newdata[$i];
                }
            }
        }

        // batch insert and update
        if (count($newset) > 0) {
            //echo "inserting new nodes ".count($newset)."! <br/>";
            for ($i = 0; $i < count($newset); $i+=$this->_atatime) {
                $tempset = array_slice($newset, $i, $this->_atatime);
                $this->batchInsertLinks($tempset);
            }
            $this->logActivity($this->_uid, $this->_netid, "added links from file", $filename, count($newset));
        }
        $updatecount = 0;
        if (count($updateset) > 0) {
            //echo "inserting new nodes ".count($newset)."! <br/>";
            for ($i = 0; $i < count($updateset); $i+=$this->_atatime) {
                $tempset = array_slice($updateset, $i, $this->_atatime);
                $updatecount += $this->batchCheckUpdateAnno($this->_netid, $tempset);
            }
            $this->logActivity($this->_uid, $this->_netid, "updated links from file", $filename, $updatecount);
        }

        return $msgs . " -- links: added " . count($newset) . " / updated " . $updatecount . " / skipped $numskipped\n";
    }

}

?>