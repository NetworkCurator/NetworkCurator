<?php

/*
 * Class handling requests for graph structure (list nodes, add a new node, etc.)
 * 
 */

class NCGraphs extends NCLogger {

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
            $this->_network = "";
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
        }
    }

    /**
     * Processes a request to create a new node in a network
     * 
     * @return boolean
     * @throws Exception
     */
    public function createNewNode() {

        // check that required parameters exist
        $pp = (array) $this->_params;
        $tocheck = array('network_name', 'node_name', 'node_title', 'class_name');
        if (count(array_intersect_key(array_flip($tocheck), $pp)) !== count($tocheck)) {
            throw new Exception("Missing parameters");
        }
        
        // shorthand variables
        $uid = $this->_uid;
        $netid = $this->getNetworkId($this->_network);

        // get the class id associated with the class name
        $classid = $this->getNameAnnoRootId($netid, $this->_params['class_name']);
        if (!$classid) {
            throw new Exception('Node class name does not match');
        } else {
            if ($classid['anno_status'] == NC_ACTIVE) {
                $classid = $classid['root_id'];
            } else {
                throw new Exception("Node class exists, but is inactive");
            }
        }

        // check if this node name already exists
        $nodename = $this->getNameAnnoRootId($netid, $this->_params['node_name']);
        if ($nodename) {
            throw new Exception("Node name is already taken");
        }

        // if reached here, create the new node        
        $nodeid = $this->makeRandomID(NC_TABLE_NODES, 'node_id', 'N', NC_ID_LEN);
        $sql = "INSERT INTO " . NC_TABLE_NODES . " 
            (network_id, node_id, class_id, node_status) VALUES (?, ?, ?, ?)";
        $stmt = prepexec($this->_db, $sql, [$netid, $nodeid, $classid, NC_ACTIVE]);

        // log entry for creation
        $this->logActivity($uid, $netid, "created node", $this->_params['node_name'], $this->_params['node_title']);

        // create starting annotations for the title, abstract and content        
        $nameparams = array('network_id' => $netid, 'user_id' => $uid, 'owner_id' => $uid,
            'root_id' => $nodeid, 'parent_id' => $nodeid, 'anno_level' => NC_NAME,
            'anno_text' => $this->_params['node_name']);
        $this->insertAnnoText($nameparams);
        // insert annotation for network title
        $titleparams = $nameparams;
        $titleparams['anno_text'] = $this->_params['node_title'];
        $titleparams['anno_level'] = NC_TITLE;
        $this->insertAnnoText($titleparams);
        // insert annotation for network abstract        
        $descparams = $titleparams;
        $descparams['anno_text'] = '';
        $descparams['anno_level'] = NC_ABSTRACT;
        $this->insertAnnoText($descparams);
        // insert annotation for network content (more than an abstract)
        $contentparams = $descparams;
        $contentparams['anno_level'] = NC_CONTENT;
        $this->insertAnnoText($contentparams);

        return $nodeid;
    }

    public function getAllNodes() {
        
          // shorthand variables
        $uid = $this->_uid;
        $netid = $this->getNetworkId($this->_network);

        $tn = "".NC_TABLE_NODES;
        $tat = "".NC_TABLE_ANNOTEXT;
        
        $sql = "SELECT node_id AS id, anno_text AS name 
            FROM $tn JOIN $tat 
                  ON $tn.network_id = $tat.network_id AND
                     $tn.node_id = $tat.root_id
                  WHERE $tn.network_id = ? AND $tat.anno_level = ".NC_NAME ."
                      AND $tn.node_status = ".NC_ACTIVE." AND anno_status = ".NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }
        
        return $result;
    }
    
}

?>
