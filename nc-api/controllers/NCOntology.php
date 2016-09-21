<?php

/**
 * Class handling requests for node and link classes
 * (e.g. define a new class of node or link)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCOntology extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger        
    // some variables extracted from $_params, for convenience
    protected $_network;
    protected $_netid;
    protected $_uperm;

    /**
     * Constructor 
     * 
     * @param PDO $db
     * 
     * Connection to the NC database
     * 
     * @param array $params
     * 
     * array with parameters
     */
    public function __construct($db, $params) {

        if (isset($params['network_name'])) {
            $this->_network = $params['network_name'];
        } else {
            throw new Exception("Missing required parameter network_name");
        }
        unset($params['network_name']);

        parent::__construct($db, $params);

        // all functions will need to know the network id code
        $this->_netid = $this->getNetworkId($this->_network, true);

        // check that the user has permissions to view this network   
        $this->_uperm = $this->getUserPermissionsNetID($this->_netid, $this->_uid);
        if ($this->_uperm < NC_PERM_VIEW) {
            throw new Exception("Insufficient permissions to query ontology");
        }
    }

    /**
     * Similar to listOntology with parameter ontology='nodes'
     */
    public function getNodeOntology() {
        $this->_params['ontology'] = 'nodes';
        return $this->getOntology();
    }

    /**
     * Similar to listOntology with parameter ontology='links'
     */
    public function getLinkOntology() {
        $this->_params['ontology'] = 'links';
        return $this->getOntology();
    }

    /**
     * Looks all the classes associated with nodes or links in a network
     * 
     * @param logical idkeys
     * 
     * set true to get an array indexed by class_id. set false for indexes by class_name
     * 
     * @return array of classes
     * 
     * @throws Exception
     * 
     */
    public function getOntology() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["ontology"]);

        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;

        // query the classes table for this network
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, directional, class_status,
            anno_text AS class_name
            FROM $tc 
              JOIN $tat 
                  ON $tc.class_id=$tat.root_id AND $tc.network_id=$tat.network_id              
                WHERE $tc.network_id = ? AND $tat.root_type = " . NC_CLASS . " 
                  AND $tat.anno_status = " . NC_ACTIVE . "
                  AND $tat.anno_type = " . NC_NAME;
        if ($params['ontology'] === "links") {
            $sql .= " AND connector=1";
        } else if ($params['ontology'] === "nodes") {
            $sql .= " AND connector=0";
        } else {
            throw new Exception("Unrecognized ontology");
        }
        $sql .= " ORDER BY parent_id, class_name";
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[$row['class_name']] = $row;
        }
        return $result;
    }

    /**
     * Perform the work associated with createNewClass().
     * This function does not perform locking and without logging.     
     * 
     * @param array $params
     * 
     * array of parameters. Assumed to contain the following:
     * 
     * "parent_name", "connector", "directional", "class_name", "class_title"
     * 
     * The array can contain more items - they will be ignored
     * 
     */
    protected function createNewClassWork($params) {

        if (strlen($params['class_name']) < 2) {
            throw new Exception("Class name too short");
        }      

        // check if the requested class name and parent_id exist         
        $classinfo = $this->getClassInfoFromName($params['class_name'], false);        
        if ($classinfo != null) {
            throw new Exception("Class name already exists");
        }        
        $parentid = 0;
        if ($params['parent_name'] != '') {
            $parentclass = $this->getClassInfoFromName($params['parent_name']);
            if ($params['directional'] < $parentclass['directional']) {
                throw new Exception("Incompatible directional settings");
            }
            if ($params['connector'] != $parentclass['connector']) {
                throw new Exception("Incompatible connector settings");
            }
            $parentid = $parentclass['class_id'];
        }

        // if reached here, the class is ok to be inserted
        // create/insert the new class into the classes table
        $sql = "INSERT INTO " . NC_TABLE_CLASSES . "
                   (network_id, parent_id, connector, directional) 
                   VALUES (?, ?, ?, ?)";
        $this->qPE($sql, [$this->_netid, $parentid, $params['connector'], $params['directional']]);
        $newid = $this->lID();

        // create an annotation entry (registers the name of the class)         
        $this->insertNewAnnoSet($this->_netid, $this->_uid, $newid, NC_CLASS, $params['class_name'], $params['class_name'], $params['class_name'], $params['class_name']);

        return $newid;
    }

    /**
     * Process request to define a new ontology class. 
     * The function inserts a new entry into the classes table and accompanying 
     * annotations in anno_text.
     * 
     * @return int
     * 
     * an id integer identifying the new class
     * 
     * @throws Exception
     */
    public function createNewClass() {
        
        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["parent_name",
            "connector", "directional", "class_name"]);                
                
        // perform changes in the DB.
        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        $newid = $this->createNewClassWork($params);
        $this->dbunlock();

        // log the activity
        $this->logActivity($this->_uname, $this->_netid, "created new class", $params['class_name'], $newid);

        return $newid;
    }

    /**
     * Fetch information about one class given its name (requires lookup in anno_text)
     * 
     * @param type $classname
     * @return type
     * @throws Exception
     */
    protected function getClassInfoFromName($classname, $throw = true) {
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, 
            directional, class_status, anno_text AS class_name, anno_id, anno_name, 
            datetime, owner_id
            FROM $tc JOIN $tat 
                ON $tc.class_id=$tat.root_id AND $tc.network_id=$tat.network_id
              WHERE $tc.network_id = ? AND $tat.root_type = " . NC_CLASS . " 
                  AND $tat.anno_text = ?
                  AND $tat.anno_type= " . NC_NAME . "
                  AND $tat.anno_status=" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classname]);
        $classinfo = $stmt->fetch();
        if (!$classinfo) {
            if ($throw) {
                throw new Exception("Class '$classname' does not exist");
            } else {
                return null;
            }
        }
        return $classinfo;
    }

    /**
     * Perform the DB work associated with updateClass().
     * This function does not perform any table locking or logging.
     * 
     * @param array $params
     * 
     * array of parameters. assumed to contain the following:
     * "class_name", "parent_name",
     * "connector", "directional", 
     * "class_newname", "class_status"
     */
    protected function updateClassWork($params) {

        if ($params["class_status"] != 1) {
            throw new Exception("Use a different function to change class status");
        }
        if (strlen($params['class_newname']) < 2) {
            throw new Exception("Class name too short");
        }

        // fetch the current information about this class
        $olddata = $this->getClassInfoFromName($params['class_name']);
        $parentid = 0;
        if ($params['parent_name'] != '') {
            $parentdata = $this->getClassInfoFromName($params['parent_name']);
            $parentid = $parentdata['class_id'];
        }

        // check that new data is different from existing data
        $Q1 = $parentid == $olddata['parent_id'];
        $Q2 = $params['class_newname'] == $params['class_name'];
        $Q3 = $params['directional'] == $olddata['directional'];
        $Q4 = $params['connector'] == $olddata['connector'];
        $Q5 = $params['class_status'] == $olddata['class_status'];
        if ($Q1 && $Q2 && $Q3 && $Q4 && $Q5) {
            throw new Exception("Update is consistent with existing data");
        }
        if (!$Q4) {
            throw new Exception("Cannot toggle connector status");
        }
        if (!$Q5) {
            throw new Exception("Cannot toggle status (use another function for that)");
        }

        // if the parent is non-trivial, collect its data
        if ($params['parent_name'] !== "") {
            // connector status must match
            if ($parentdata['connector'] != $params['connector']) {
                throw new Exception("Incompatible class/parent connector status");
            }

            // for links that are non-directional, make sure the parent is also 
            if (!$params['directional'] && $params['connector']) {
                if ($parentdata['directional'] > $params['directional']) {
                    throw new Exception("Incompatible directional status (parent is a directional link)");
                }
            }
        }

        // if here, the class needs updating.
        $result = 0;
        if (!$Q2) {
            // here, the class name needs updating, but is it available?
            $sql = "SELECT anno_id FROM " . NC_TABLE_ANNOTEXT . " WHERE
                     network_id = ? AND anno_type=" . NC_NAME . " 
                         AND root_type = " . NC_CLASS . " AND anno_text = ? 
                         AND anno_status=" . NC_ACTIVE;
            //echo "---- ".$params['class_newname']." === ";
            $stmt = $this->qPE($sql, [$this->_netid, $params['class_newname']]);
            if ($stmt->fetch()) {
                throw new Exception("Class name already exists");
            }

            // update the class name and log the activity
            $pp = $this->subsetArray($olddata, ['owner_id', 'datetime', 'anno_name', 'anno_id']);
            $pp = array_merge($pp, ['network_id' => $this->_netid, 'root_type' => NC_CLASS,
                'parent_id' => $olddata['class_id'], 'root_id' => $olddata['class_id'],
                'anno_text' => $params['class_newname'], 'anno_type' => NC_NAME]);
            $this->updateAnnoText($pp);
            $result += 1;
        }

        if (!$Q1 || !$Q3) {
            // update the class structure and log the activity
            $this->updateClassStructure($parentid, $params['directional'], $params['class_status'], $olddata['class_id']);
            $result +=2;
        }

        return $result;
    }

    /**
     * Changes properties or annotations associated with a given class
     * 
     * @return string
     * 
     * @throws Exception
     */
    public function updateClass() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["class_name", "parent_name",
            "connector", "directional", "class_newname", "class_status"]);

        // perform the work of updating the class
        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES, NC_TABLE_ACTIVITY]);
        $action = $this->updateClassWork($params);
        $this->dbunlock();

        // perform logging based on what actions were performed
        if ($action % 2 == 1) {
            $this->logActivity($this->_uname, $this->_netid, "updated class name", $params['class_name'], $params['class_newname']);
        }
        if ($action > 1) {
            $value = $params['parent_name'] . "," . $params['directional'] . "," . $params['class_status'];
            $this->logActivity($this->_uname, $this->_netid, "updated class properties for class", $params['class_newname'], $value);
        }

        return 1;
    }

    /**
     * Helper function applies an update transformation on a 
     * 
     * @param type $parentid
     * @param type $directional
     * @param type $classstatus
     * @param type $classid
     */
    protected function updateClassStructure($parentid, $directional, $status, $classid) {
        $sql = "UPDATE " . NC_TABLE_CLASSES . "  SET 
            parent_id= ? , directional= ?, class_status= ? WHERE class_id = ?";
        $this->qPE($sql, [$parentid, $directional, $status, $classid]);
    }

    /**
     * Either deprecate or remove a class from the db
     * 
     * @param type $classname
     * @throws Exception
     */
    protected function removeClassWork($classname) {

        // fetch information about the class
        $olddata = $this->getClassInfoFromName($classname);
        $classid = $olddata['class_id'];

        // class exists in db. Check if it has been used already in a nontrivial way
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $tan = "" . NC_TABLE_ANNONUM;

        // does the class have active descendants?
        $sql = "SELECT class_id FROM $tc WHERE network_id = ? 
                    AND parent_id = ? AND class_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        if ($stmt->fetch()) {
            throw new Exception("Cannot remove because class has active descendants");
        }

        // are there nodes or links that use this class?
        $sql = "SELECT COUNT(*) AS count FROM ";
        if ($olddata['connector']) {
            $sql .= NC_TABLE_LINKS;
        } else {
            $sql .= NC_TABLE_NODES;
        }
        $sql .= " WHERE network_id = ? AND class_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching class usage");
        }
        if ($result['count'] == 0) {
            // class is not used - remove it permanently from all tables            
            $sql = "DELETE FROM $tc WHERE network_id = ? AND class_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            $sql = "DELETE FROM $tat WHERE network_id = ? AND root_id = ? AND root_type= " . NC_CLASS;
            $this->qPE($sql, [$this->_netid, $classid]);
            $sql = "DELETE FROM $tan WHERE network_id = ? AND root_id = ? AND root_type= " . NC_CLASS;
            $this->qPE($sql, [$this->_netid, $classid]);

            $result = true; //"Class has been removed entirely";
        } else {
            // class is used - set as inactive
            $sql = "UPDATE $tc SET class_status = " . NC_DEPRECATED . " WHERE 
                     network_id = ? AND class_id = ? ";
            $this->qPE($sql, [$this->_netid, $classid]);

            $result = false; //"Class id has already been used";
        }
    }

    /**
     * Either deletes or inactivates a given class
     */
    public function removeClass() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["class_name"]);

        // perform the removal action
        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_ANNOTEXT, NC_TABLE_ANNONUM,
            NC_TABLE_ACTIVITY, NC_TABLE_LINKS, NC_TABLE_NODES]);
        $action = $this->removeClassWork($params['class_name']);
        $this->dbunlock();

        // log the action
        if ($action) {
            $this->logActivity($this->_uname, $this->_netid, "deleted class", $params['class_name'], $params['class_name']);
        } else {
            $this->logActivity($this->_uname, $this->_netid, "deprecated class", $params['class_name'], $params['class_name']);
        }

        return $action;
    }

    /**
     * 
     * @param string $classname
     */
    protected function activateClassWork($classname) {

        // check class actually needs activating
        $classinfo = $this->getClassInfoFromName($classname);
        if ($classinfo['class_status'] != NC_DEPRECATED) {
            throw new Exception("Class is not deprecated");
        }

        $tc = "" . NC_TABLE_CLASSES;
        // parent class must be active
        if ($classinfo['parent_id'] != '') {
            $sql = "SELECT class_status FROM $tc WHERE class_id = ?";
            $stmt = $this->qPE($sql, [$classinfo['parent_id']]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Error reading parent class");
            } else {
                if ($result['class_status'] != NC_ACTIVE) {
                    throw new Exception("Class cannot be activated because parent is deprecated");
                }
            }
        }

        // finally just set new status 
        $sql = "UPDATE " . NC_TABLE_CLASSES . " SET class_status = " . NC_ACTIVE . "
            WHERE network_id = ? AND class_id = ? ";
        $this->qPE($sql, [$this->_netid, $params['class_id']]);
    }

    /**
     * Used to turn a deprecated class status into an active class status
     * 
     * This is a rather simple function as it does not affect any of the nodes/links.
     * 
     * @return boolean
     * 
     * Usually returns true upon successful completion. Otherwise throws exceptions.
     * 
     * @throws Exception
     * 
     */
    public function activateClass() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["class_name"]);

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        $this->activateClassWork($params['class_name']);
        $this->dbunlock();

        // log the activity
        $this->logActivity($this->_uname, $this->_netid, "re-activated class", $params['class_name'], $params['class_name']);

        return true;
    }

}

?>
