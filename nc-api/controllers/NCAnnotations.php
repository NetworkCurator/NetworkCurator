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
        $pp = ['anno_id'=>$annoid, 'anno_text'=>$annotext, 'owner_id'=>$result['owner_id'],
            'user_id'=>$this->_uid,
            'root_id'=>$result['root_id'], 'parent_id'=>$result['parent_id'],
            'network_id'=>$netid, 'anno_level'=>$result['anno_level']];
        $this->updateAnnoText($pp);
        
        // log the action
        $this->logActivity($this->_uid, $netid, "updated annotation text for", $annoid, $annotext);
        
        return true;
    }

}

?>