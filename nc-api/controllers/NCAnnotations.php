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

        // check for required parameters
        if (isset($params['network'])) {
            $this->_network = $params['network'];
        } else {
            throw new Exception("Missing required parameter network");
        }
        unset($params['network']);

        parent::__construct($db, $params);

        // find the network id that corresponds to the name
        $this->_netid = $this->getNetworkId($this->_network, true);

        // fetch user permissions 
        $this->_uperm = $this->getUserPermissions($this->_netid, $this->_uid);
    }

    /**
     * processes request to update the text of an existing annotation
     * 
     * @return boolean
     * @throws Exception
     */
    public function updateAnnotationText() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["anno_id", "anno_text"]);
        $params['user_id'] = $this->_uid;
        $params['network_id'] = $this->_netid;

        // check if user has permission to view the table        
        if ($this->_uperm < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // need to find details of the existing annotation, user_id, root_id, etc.  
        $sql = "SELECT datetime, owner_id, root_id, parent_id, anno_type FROM " . NC_TABLE_ANNOTEXT . "
                WHERE network_id = ? AND anno_id = ? AND anno_status =" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $params['anno_id']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error retrieving current annotation");
        }

        // curator can edit anything, but others can only edit their own items
        if ($this->_uperm < NC_PERM_CURATE) {
            if ($result['owner_id'] !== $this->_uid) {
                throw new Exception("Inconsistent annotation ownership");
            }
        }

        // if reached here, it is safe to edit the annotation        
        $this->updateAnnoText(array_merge($params, $result));

        // log the action
        $this->logActivity($this->_uid, $this->_netid, "updated annotation text for", $params['anno_id'], $params['anno_text']);

        return true;
    }

    /**
     * 
     * @return int
     * @throws Exception
     */
    public function createNewComment() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id", "anno_text", "root_id", "parent_id"]);

        // check if user has permission to view the table          
        if ($this->_uperm < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // determine the comment level from the parent id
        $annolevel = NC_COMMENT;
        $tat = "" . NC_TABLE_ANNOTEXT;

        if ($params['parent_id'] === '') {
            $params['parent_id'] = $this->_netid;
        } else {
            // check that the parent id is valid and active
            $sql = "SELECT anno_type FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $params['parent_id']]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Parent annotation does not exist");
            }
            if ($result['anno_type'] == NC_COMMENT) {
                $annolevel = NC_SUBCOMMENT;
            }
        }
        // check the root annotation is valid and active
        $sql = "SELECT anno_status FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $params['root_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Root annotation does not exist");
        }

        // if reached here, insert the comment, log it, and finish
        $pp = array('network_id' => $this->_netid,
            'owner_id' => $this->_uid, 'user_id' => $this->_uid,
            'root_id' => $params['root_id'], 'parent_id' => $params['parent_id'],
            'anno_text' => $params['anno_text'], 'anno_type' => $annolevel);
        $newid = $this->insertAnnoText($pp);
        $this->logActivity($this->_uid, $this->_netid, 'wrote a comment', $newid, $params['anno_text']);

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

        // fetch all the comments
        $sql = "SELECT datetime, modified, owner_id, user_id, anno_id, 
                       parent_id, anno_text 
                  FROM " . NC_TABLE_ANNOTEXT . "
                  WHERE network_id = ? and root_id = ? AND anno_status = " . NC_ACTIVE
                . " ORDER BY anno_type, datetime";
        $stmt = $this->qPE($sql, [$this->_netid, $params['root_id']]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['anno_id']] = $row;
        }

        return $result;
    }

}

?>