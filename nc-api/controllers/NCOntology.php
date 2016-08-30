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
    private $_uid;
    private $_netid;

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

        $this->_db = $db;
        $this->_params = $params;

        // check for required parameters
        if (isset($params['network_name'])) {
            $this->_network = $this->_params['network_name'];
        } else {
            throw new Exception("NCOntology requires parameter network_name");
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCOntology requires parameter user_id");
        }

        // all function will need to know the network id code
        $this->_netid = $this->getNetworkId($this->_params['network_name']);
        if ($this->_netid == "") {
            throw new Exception("Network does not exist");
        }

        // check that the user has permissions to view this network        
        if ($this->getUserPermissionsNetID($this->_netid, $this->_uid) < NC_PERM_VIEW) {
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
     * 
     * @return array of classes
     * 
     * @throws Exception
     * 
     */
    public function getOntology() {

        if (!isset($this->_params['ontology'])) {
            throw new Exception("Unspecified ontology");
        }

        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;

        // query the classes table for this network
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, directional, class_status,
            anno_text AS class_name
            FROM $tc 
              JOIN $tat 
                  ON $tc.class_id=$tat.parent_id AND $tc.network_id=$tat.network_id              
                WHERE $tc.network_id = ? 
                  AND $tat.anno_status = " . NC_ACTIVE . "
                  AND $tat.anno_level = " . NC_NAME;
        if ($this->_params['ontology'] === "links") {
            $sql .= " AND connector=1";
        } else if ($this->_params['ontology'] === "nodes") {
            $sql .= " AND connector=0";
        } else {
            throw new Exception("Unrecognized ontology");
        }
        $sql .= " ORDER BY parent_id, class_name";

        $stmt = prepexec($this->_db, $sql, [$this->_netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[$row['class_id']] = $row;
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

        // check for required inputs
        if (!isset($this->_params['parent_id'])) {
            throw new Exception("Unspecified class parent");
        }
        if (!isset($this->_params['connector'])) {
            throw new Exception("Unspecified connector");
        }
        if (!isset($this->_params['directional'])) {
            $this->_params['directional'] = 0;
        }
        if (!isset($this->_params['class_name'])) {
            throw new Exception("Unspecified class name");
        }

        if (strlen($this->_params['class_name']) < 2) {
            throw new Exception("Class name too short");
        }

        // check if the requested class name and parent_id exist
        $newname = $this->_params['class_name'];
        $sql = "SELECT anno_text, anno_status FROM " . NC_TABLE_ANNOTEXT . " 
             WHERE network_id = ? AND anno_text = ? AND anno_level = " . NC_NAME;
        $stmt = prepexec($this->_db, $sql, [$this->_netid, $newname]);
        $result = $stmt->fetch();
        if ($result) {
            if ($result['anno_status'] != NC_ACTIVE) {
                throw new Exception("Class name already exists, but is inactive");
            } else {
                throw new Exception("Class name already exists");
            }
        }

        $newparent = $this->_params['parent_id'];
        $newconnector = $this->_params['connector'];
        $newdirectional = $this->_params['directional'];
        if ($newparent != '') {
            $sql = "SELECT class_id, connector, directional FROM " . NC_TABLE_CLASSES . " WHERE class_id=?";
            $stmt = prepexec($this->_db, $sql, [$newparent]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Parent id does not exist");
            } else {
                if ($newdirectional < $result['directional']) {
                    throw new Exception("Incompatible directional settings");
                }
                if ($newconnector != $result['connector']) {
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
        $stmt = $this->_db->prepare($sql);
        $pp = ['network_id' => $this->_netid, 'class_id' => $newid, 'parent_id' => $newparent,
            'connector' => $newconnector, 'directional' => $newdirectional];
        $stmt = prepexec($this->_db, $sql, $pp);

        // create an annotation entry (registers the name of the class)         
        $pp = ['network_id' => $this->_netid, 'user_id' => $this->_uid, 'root_id' => $newid,
            'parent_id' => $newid, 'anno_text' => $newname, 'anno_level' => NC_NAME];
        $this->insertAnnoText($pp);

        // log the activity
        $this->logActivity($this->_params['user_id'], $this->_netid, "Created new class", $newname, $newid);

        return $newid;
    }

    /**
     * Changes properties or annotations associated with a given class
     * 
     * @return string
     * 
     * @throws Exception
     */
    public function updateClass() {

        // check for required inputs
        if (!isset($this->_params['parent_id'])) {
            throw new Exception("Unspecified class parent");
        }
        if (!isset($this->_params['connector'])) {
            throw new Exception("Unspecified connector");
        }
        if (!isset($this->_params['directional'])) {
            $this->_params['directional'] = 0;
        }
        if (!isset($this->_params['class_name'])) {
            throw new Exception("Unspecified class name");
        }
        if (!isset($this->_params['parent_id'])) {
            throw new Exception("Unspecified parent id");
        }

        // fetch the current information about this class
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, 
            directional, class_status, anno_text AS class_name, anno_id 
            FROM $tc JOIN $tat ON $tc.class_id=$tat.root_id 
              WHERE $tc.class_id = ? AND $tat.anno_level= " . NC_NAME . "
                  AND $tat.anno_status=" . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$this->_params['class_id']]);
        $olddata = $stmt->fetch();
        if (!$olddata) {
            throw new Exception("Class does not exist");
        }

        // check that new data is different from existing data
        $Q1 = $this->_params['parent_id'] == $olddata['parent_id'];
        $Q2 = $this->_params['class_name'] == $olddata['class_name'];
        $Q3 = $this->_params['directional'] == $olddata['directional'];
        $Q4 = $this->_params['connector'] == $olddata['connector'];
        $Q5 = $this->_params['class_status'] == $olddata['class_status'];
        if ($Q1 && $Q2 && $Q3 && $Q4 && $Q5) {
            throw new Exception("Update is consistent with existing data");
        }
        // check that the new connector and old connector information matches
        if (!$Q4) {
            throw new Exception("Cannot toggle connector status");
        }

        // if the parent is non-trivial, collect its data
        if ($this->_params['parent_id'] !== "") {
            $sql = "SELECT class_id, connector, directional FROM $tc 
            WHERE class_id = ?";
            $stmt = prepexec($this->_db, $sql, [$this->_params['parent_id']]);
            $parentdata = $stmt->fetch();
            if (!$parentdata) {
                throw new Exception("Could not retrieve parent data");
            }

            // connector status must match
            if ($parentdata['connector'] != $this->_params['connector']) {
                throw new Exception("Incompatible class/parent connector status");
            }

            // for links that are non-directional, make sure the parent is also 
            if (!$this->_params['directional'] && $this->_params['connector']) {
                if ($parentdata['directional'] > $this->_params['directional']) {
                    throw new Exception("Incompatible parent directional status");
                }
            }
        }

        // if here, the class needs updating. 
        if (!$Q2) {
            // here, the class name needs updating, but is it available
            $sql = "SELECT anno_id FROM $tat WHERE
                     network_id = ? AND anno_level=" . NC_NAME . " AND
                         root_id LIKE 'C%' AND anno_text = ? AND anno_status=" . NC_ACTIVE;
            $stmt = prepexec($this->_db, $sql, [$this->_netid, $this->_params['class_name']]);
            if ($stmt->fetch()) {
                throw new Exception("Class name already exists");
            }

            // update the class name and log the activity
            $pp = ['network_id' => $this->_netid, 'user_id' => $this->_uid,
                'root_id' => $this->_params['class_id'],
                'parent_id' => $this->_params['class_id'],
                'anno_text' => $this->_params['class_name'],
                'anno_id' => $olddata['anno_id'],
                'anno_level' => NC_NAME];
            $this->updateAnnoText($pp);
            $this->logActivity($this->_uid, $this->_netid, "Updated class name", $pp['anno_text'], $pp['parent_id']);
        }
        if (!$Q1 || !$Q3) {
            // update the class structure and log the activity
            $sql = "UPDATE $tc SET
                      parent_id= :parent_id , directional= :directional,
                      class_status= :class_status
                    WHERE network_id = :network_id AND class_id = :class_id";
            $pp = ['parent_id' => $this->_params['parent_id'],
                'directional' => $this->_params['directional'],
                'class_status' => $this->_params['class_status'],
                'network_id' => $this->_netid,
                'class_id' => $this->_params['class_id']];
            $stmt = prepexec($this->_db, $sql, $pp);

            $value = $pp['parent_id'] . "," . $pp['directional'] . "," . $pp['class_status'];
            $this->logActivity($this->_uid, $this->_netid, "Updated class properties for class", $this->_params['class_name'], $value);
        }

        return 1;
    }

}

?>
