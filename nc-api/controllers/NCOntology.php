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
    private $_network;
    private $_netid;
    private $_uperm;

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

        parent::__construct($db, $params);

        if (isset($params['network_name'])) {
            $this->_network = $this->_params['network_name'];
        } else {
            throw new Exception("Missing required parameter network_name");
        }

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
    public function getNodeOntology($idkeys = true) {
        $this->_params['ontology'] = 'nodes';
        return $this->getOntology($idkeys);
    }

    /**
     * Similar to listOntology with parameter ontology='links'
     */
    public function getLinkOntology($idkeys = true) {
        $this->_params['ontology'] = 'links';
        return $this->getOntology($idkeys);
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
    public function getOntology($idkeys = true) {

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
                WHERE $tc.network_id = ? 
                  AND $tat.anno_status = " . NC_ACTIVE . "
                  AND $tat.anno_level = " . NC_NAME;
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
        if ($idkeys == true) {
            while ($row = $stmt->fetch()) {
                $result[$row['class_id']] = $row;
            }
        } else {
            while ($row = $stmt->fetch()) {
                $result[$row['class_name']] = $row;
            }
        }
        return $result;
    }

    /**
     * Define a new class. The function inserts a new entry into the 
     * classes table and accompanying annotations in anno_text
     * 
     * @return type
     * @throws Exception
     */
    public function createNewClass() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["parent_id",
            "connector", "directional", "class_name"]);

        if (strlen($params['class_name']) < 2) {
            throw new Exception("Class name too short");
        }

        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        
        // check if the requested class name and parent_id exist        
        $sql = "SELECT anno_text, anno_status FROM " . NC_TABLE_ANNOTEXT . " 
             WHERE network_id = ? AND anno_text = ? AND anno_level = " . NC_NAME;
        $stmt = $this->qPE($sql, [$this->_netid, $params['class_name']]);
        $result = $stmt->fetch();
        if ($result) {
            if ($result['anno_status'] != NC_ACTIVE) {
                throw new Exception("Class name already exists, but is inactive");
            } else {
                throw new Exception("Class name already exists");
            }
        }
        if ($params['parent_id'] != '') {
            $sql = "SELECT class_id, connector, directional FROM " .
                    NC_TABLE_CLASSES . " WHERE class_id = ?";
            $stmt = $this->qPE($sql, [$params['parent_id']]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Parent id does not exist");
            } else {
                if ($params['directional'] < $result['directional']) {
                    throw new Exception("Incompatible directional settings");
                }
                if ($params['connector'] != $result['connector']) {
                    throw new Exception("Incompatible connector settings");
                }
            }
        }

        // if reached here, the class is ok to be inserted
        $newid = $this->makeRandomID(NC_TABLE_CLASSES, "class_id", "C", NC_ID_LEN);

        // create/insert the new class into the classes table
        $sql = "INSERT INTO " . NC_TABLE_CLASSES . "
                   (network_id, class_id, parent_id, connector, directional) 
                   VALUES 
                   (:network_id, :class_id, :parent_id, :connector, :directional)";        
        $pp = ['network_id' => $this->_netid, 'class_id' => $newid,
            'parent_id' => $params['parent_id'],
            'connector' => $params['connector'], 'directional' => $params['directional']];
        $stmt = $this->qPE($sql, $pp);
        
        // create an annotation entry (registers the name of the class)         
        $pp = ['network_id' => $this->_netid,
            'owner_id' => $this->_uid, 'user_id' => $this->_uid,
            'root_id' => $newid, 'parent_id' => $newid,
            'anno_text' => $params['class_name'], 'anno_level' => NC_NAME];
        $this->insertAnnoText($pp);

        $this->dbunlock();
        
        // log the activity
        $this->logActivity($this->_params['user_id'], $this->_netid, "created new class", $params['class_name'], $newid);

        return $newid;
    }

    /**
     * Fetches current information on a given class
     * 
     * @param string $classid
     * 
     * id string
     * 
     * @return array
     * 
     * data describing a given class_name
     *
     * @throws Exception
     * 
     * when the classid does not match db records
     * 
     */
    private function getClassInfo($classid) {
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, 
            directional, class_status, anno_text AS class_name, anno_id, owner_id
            FROM $tc JOIN $tat 
                ON $tc.class_id=$tat.root_id AND $tc.network_id=$tat.network_id
              WHERE $tc.network_id = ? AND $tc.class_id = ? 
                  AND $tat.anno_level= " . NC_NAME . "
                  AND $tat.anno_status=" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        $classinfo = $stmt->fetch();
        if (!$classinfo) {
            throw new Exception("Class $classid does not exist");
        }
        return $classinfo;
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
        $params = $this->subsetArray($this->_params, ["class_id", "parent_id", 
            "connector", "directional", "class_name", "class_status"]);

        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;

        if ($params["class_status"] != 1) {
            throw new Exception("Use a different function to change class status");
        }

        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        
        // fetch the current information about this class
        $olddata = $this->getClassInfo($params['class_id']);

        // check that new data is different from existing data
        $Q1 = $params['parent_id'] == $olddata['parent_id'];
        $Q2 = $params['class_name'] == $olddata['class_name'];
        $Q3 = $params['directional'] == $olddata['directional'];
        $Q4 = $params['connector'] == $olddata['connector'];
        $Q5 = $params['class_status'] == $olddata['class_status'];
        if ($Q1 && $Q2 && $Q3 && $Q4 && $Q5) {
            $this->dbunlock();
            throw new Exception("Update is consistent with existing data");
        }
        // check that the new connector and old connector information matches
        if (!$Q4) {
            $this->dbunlock();
            throw new Exception("Cannot toggle connector status");
        }
        if (!$Q5) {
            $this->dbunlock();
            throw new Exception("Cannot toggle status (use another function for that)");
        }

        // if the parent is non-trivial, collect its data
        if ($params['parent_id'] !== "") {
            $sql = "SELECT class_id, connector, directional FROM $tc 
            WHERE class_id = ?";
            $stmt = $this->qPE($sql, [$params['parent_id']]);
            $parentdata = $stmt->fetch();
            if (!$parentdata) {
                $this->dbunlock();
                throw new Exception("Could not retrieve parent data");
            }

            // connector status must match
            if ($parentdata['connector'] != $params['connector']) {
                $this->dbunlock();
                throw new Exception("Incompatible class/parent connector status");
            }

            // for links that are non-directional, make sure the parent is also 
            if (!$params['directional'] && $params['connector']) {
                if ($parentdata['directional'] > $params['directional']) {
                    $this->dbunlock();
                    throw new Exception("Incompatible directional status (parent is a directional link)");
                }
            }
        }

        // if here, the class needs updating. 
        if (!$Q2) {
            // here, the class name needs updating, but is it available
            $sql = "SELECT anno_id FROM $tat WHERE
                     network_id = ? AND anno_level=" . NC_NAME . " AND
                         root_id LIKE 'C%' AND anno_text = ? AND anno_status=" . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $params['class_name']]);
            if ($stmt->fetch()) {
                $this->dbunlock();
                throw new Exception("Class name already exists");
            }

            // update the class name and log the activity
            $pp = ['network_id' => $this->_netid, 'user_id' => $this->_uid,
                'owner_id' => $olddata['owner_id'],
                'root_id' => $params['class_id'],
                'parent_id' => $params['class_id'],
                'anno_text' => $params['class_name'],
                'anno_id' => $olddata['anno_id'],
                'anno_level' => NC_NAME];
            $this->updateAnnoText($pp);
            $this->logActivity($this->_uid, $this->_netid, "updated class name", $pp['anno_text'], $pp['parent_id']);
        }
        if (!$Q1 || !$Q3) {
            // update the class structure and log the activity
            $sql = "UPDATE $tc SET
                      parent_id= :parent_id , directional= :directional,
                      class_status= :class_status
                    WHERE network_id = :network_id AND class_id = :class_id";
            $pp = ['parent_id' => $params['parent_id'],
                'directional' => $params['directional'],
                'class_status' => $params['class_status'],
                'network_id' => $this->_netid,
                'class_id' => $params['class_id']];
            $this->qPE($sql, $pp);

            $value = $pp['parent_id'] . "," . $pp['directional'] . "," . $pp['class_status'];
            $this->logActivity($this->_uid, $this->_netid, "updated class properties for class", $params['class_name'], $value);
        }
        
        $this->dbunlock();
        
        return 1;
    }

    /**
     * Either deletes or inactivates a given class
     */
    public function removeClass() {

        // check that required inputs are defined
        $classid = $this->subsetArray($this->_params, ["class_id"])['class_id'];

        // fetch information about the class
        $olddata = $this->getClassInfo($classid);

        // class exists in db. Check if it has been used already in a nontrivial way
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $tan = "" . NC_TABLE_ANNONUM;

        // does the class have active descendants?
        $sql = "SELECT class_id FROM $tc WHERE network_id = ? 
                    AND parent_id = ? AND class_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        if ($stmt->fetch()) {
            throw new Exception("Cannot remove: class has active descendants");
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
            $sql = "DELETE FROM $tat WHERE network_id = ? AND root_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            $sql = "DELETE FROM $tan WHERE network_id = ? AND root_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            // log the event
            $this->logActivity($this->_uid, $this->_netid, "deleted class", $olddata['class_name'], $classid);

            return true; //"Class has been removed entirely";
        } else {
            // class is used - set as inactive
            $sql = "UPDATE $tc SET class_status = " . NC_DEPRECATED . " WHERE 
                     network_id = ? AND class_id = ? ";
            $this->qPE($sql, [$this->_netid, $classid]);

            // log the event
            $this->logActivity($this->_uid, $this->_netid, "deprecated class", $olddata['class_name'], $classid);

            return false; //"Class id has already been used";
        }
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
        $params = $this->subsetArray($this->_params, ["class_id"]);

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        // check class actually needs activating
        $classinfo = $this->getClassInfo($params['class_id']);
        if ($classinfo['class_status'] != NC_DEPRECATED) {
            throw new Exception("Class is not deprecated");
        }

        // parent class must be active
        if ($classinfo['parent_id'] != '') {
            $parentinfo = $this->getClassInfo($classinfo['parent_id']);
            if ($parentinfo['class_status'] != NC_ACTIVE) {
                throw new Exception("Class cannot be activated because parent is deprecated");
            }
        }

        // finally just set new status 
        $sql = "UPDATE " . NC_TABLE_CLASSES . " SET class_status = " . NC_ACTIVE . "
            WHERE network_id = ? AND class_id = ? ";
        $this->qPE($sql, [$this->_netid, $params['class_id']]);

        // log the activity
        $this->logActivity($this->_uid, $this->_netid, "re-activated class", $classinfo['class_name'], $params['class_id']);

        return true;
    }

}

?>
