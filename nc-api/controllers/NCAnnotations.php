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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id",
            "anno_id", "anno_text"]);

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network, true);

        // check if user has permission to view the table   
        $userpermissions = $this->getUserPermissionsNetID($netid, $this->_uid);
        if ($userpermissions < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // need to find details of the existing annotation, user_id, root_id, etc.
        $tat = "" . NC_TABLE_ANNOTEXT;

        $sql = "SELECT owner_id, root_id, parent_id, anno_level FROM $tat 
                WHERE network_id = ? AND anno_id = ? AND anno_status =" . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid, $params['anno_id']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error retrieving annotation owner");
        }

        // curator can edit anything, but others can only edit their own items
        if ($userpermissions < NC_PERM_CURATE) {
            if ($result['owner_id'] !== $this->_uid) {
                throw new Exception("Inconsistent annotation ownership");
            }
        }

        // if reached here, it is safe to edit the annotation
        $pp = ['anno_id' => $params['anno_id'], 'anno_text' => $params['anno_text'],
            'owner_id' => $result['owner_id'], 'user_id' => $this->_uid,
            'root_id' => $result['root_id'], 'parent_id' => $result['parent_id'],
            'network_id' => $netid, 'anno_level' => $result['anno_level']];
        $this->updateAnnoText($pp);

        // log the action
        $this->logActivity($this->_uid, $netid, "updated annotation text for", $params['anno_id'], $params['anno_text']);

        return true;
    }

    /**
     * 
     * @return int
     * @throws Exception
     */
    public function createNewComment() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id",
            "anno_text", "root_id", "parent_id"]);

        $tat = "" . NC_TABLE_ANNOTEXT;

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network, true);

        // check if user has permission to view the table   
        $userpermissions = $this->getUserPermissionsNetID($netid, $this->_uid);
        if ($userpermissions < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // determine the comment level from the parent id
        $annolevel = NC_COMMENT;

        if ($params['parent_id'] === '') {
            $params['parent_id'] = $netid;
        } else {
            // check that the parent id is valid and active
            $sql = "SELECT anno_level FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
            $stmt = prepexec($this->_db, $sql, [$netid, $params['parent_id']]);
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
        $stmt = prepexec($this->_db, $sql, [$netid, $params['root_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Root annotation does not exist");
        }

        // if reached here, insert the comment and finish
        $pp = array('network_id' => $netid,
            'owner_id' => $this->_uid, 'user_id' => $this->_uid,
            'root_id' => $params['root_id'], 'parent_id' => $params['parent_id'],
            'anno_text' => $params['anno_text'], 'anno_level' => $annolevel);
        $newid = $this->insertAnnoText($pp);
        $this->logActivity($this->_uid, $netid, 'wrote a comment', $newid, $params['anno_text']);

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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["root_id"]);

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network, true);

        // fetch all the comments
        $sql = "SELECT datetime, modified, owner_id, user_id, anno_id, 
                       parent_id, anno_text 
                  FROM " . NC_TABLE_ANNOTEXT . "
                  WHERE network_id = ? and root_id = ? AND anno_status = " . NC_ACTIVE
                . " ORDER BY anno_level, datetime";
        $stmt = prepexec($this->_db, $sql, [$netid, $params['root_id']]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['anno_id']] = $row;
        }

        return $result;
    }

}

?>