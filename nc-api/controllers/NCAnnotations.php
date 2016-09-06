<?php

/**
 * Class handling requests for annotations (update an annotation, create a new annotation)
 *
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 *
 */
class NCAnnotations extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger    
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_uid;

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
            throw new Exception("NCAnnotations requires a network name");
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCAnnotations requires parameter user_id");
        }
    }

    public function updateAnnotationText() {

        if (!isset($this->_params['anno_id'])) {
            throw new Exception("Missing annotation id");
        }
        if (!isset($this->_params['anno_text'])) {
            throw new Exception("Missing annotation text");
        }

        $annoid = $this->_params['anno_id'];
        $annotext = $this->_params['anno_text'];

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network);
        if ($netid == "") {
            throw new Exception("Network does not exist");
        }

        // check if user has permission to view the table   
        $userpermissions = $this->getUserPermissionsNetID($netid, $this->_uid);
        if ($userpermissions < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // need to find details of the existing annotation, user_id, root_id, etc.
        $tat = "" . NC_TABLE_ANNOTEXT;

        $sql = "SELECT owner_id, root_id, parent_id, anno_level FROM $tat 
                WHERE network_id = ? AND anno_id = ? AND anno_status =" . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid, $annoid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error retrieving annotation owner");
        }

        // curator can edit anything, but others can only edit their own items
        if ($userpermissions < NC_PERM_CURATE) {
            if ($result['owner_id'] !== $annoid) {
                throw new Exception("Inconsistent annotation ownership");
            }
        }

        // if reached here, it is safe to edit the annotation
        $pp = ['anno_id' => $annoid, 'anno_text' => $annotext, 'owner_id' => $result['owner_id'],
            'user_id' => $this->_uid,
            'root_id' => $result['root_id'], 'parent_id' => $result['parent_id'],
            'network_id' => $netid, 'anno_level' => $result['anno_level']];
        $this->updateAnnoText($pp);

        // log the action
        $this->logActivity($this->_uid, $netid, "updated annotation text for", $annoid, $annotext);

        return true;
    }

    /**
     * 
     * @return int
     * @throws Exception
     */
    public function createNewComment() {

        // check for required input
        if (!isset($this->_params['anno_text'])) {
            throw new Exception("Missing annotation text");
        }
        if (!isset($this->_params['root_id'])) {
            throw new Exception("Missing root id");
        }
        if (!isset($this->_params['parent_id'])) {
            throw new Exception("Missing parent id");
        }

        $rootid = $this->_params['root_id'];
        $parentid = $this->_params['parent_id'];
        $annotext = $this->_params['anno_text'];
        $tat = "" . NC_TABLE_ANNOTEXT;

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network);
        if ($netid == "") {
            throw new Exception("Network does not exist");
        }

        // check if user has permission to view the table   
        $userpermissions = $this->getUserPermissionsNetID($netid, $this->_uid);
        if ($userpermissions < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // determine the comment level from the parent id
        $annolevel = NC_COMMENT;

        if ($parentid === '') {
            $parentid = $netid;
        } else {
            // check that the parent id is valid and active
            $sql = "SELECT anno_level FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
            $stmt = prepexec($this->_db, $sql, [$netid, $parentid]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Parent annotation does not exist");
            }
            if ($result['anno_level'] == NC_COMMENT) {
                $annolevel = NC_SUBCOMMENT;
            }
        }
        // check the root annotation is valid and active
        $sql = "SELECT anno_status FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid, $rootid]);
        if (!$stmt->fetch()) {
            throw new Exception("Root annotation does not exist");
        }

        // if reached here, insert the comment and finish
        $pp = array('network_id' => $netid,
            'owner_id' => $this->_uid, 'user_id' => $this->_uid,
            'root_id' => $rootid, 'parent_id' => $parentid,
            'anno_text' => $annotext, 'anno_level' => $annolevel);
        $newid = $this->insertAnnoText($pp);
        $this->logActivity($this->_uid, $netid, 'wrote a comment', $newid, $annotext);

        return $newid;
    }

    /**
     * 
     * @return array
     * 
     * associative array with comments, ordered by level and date
     * 
     * @throws Exception
     */
    public function getComments() {
        
        // check for required input        
        if (!isset($this->_params['root_id'])) {
            throw new Exception("Missing root id");
        }

        $rootid = $this->_params['root_id'];
        $tat = "" . NC_TABLE_ANNOTEXT;

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network);
        if ($netid == "") {
            throw new Exception("Network does not exist");
        }

        // fetch all the comments
        $sql = "SELECT datetime, owner_id, user_id, anno_id, 
                       parent_id, anno_text 
                  FROM $tat 
                  WHERE network_id = ? and root_id = ? AND anno_status = " . NC_ACTIVE 
                  ." ORDER BY anno_level, datetime";
        $stmt = prepexec($this->_db, $sql, [$netid, $rootid]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['anno_id']] = $row;
        }
        
        return $result;
    }

}

?>