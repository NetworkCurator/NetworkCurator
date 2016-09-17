<?php

/*
 * Class handling requests for graph structure (list nodes, add a new node, etc.)
 * 
 */

class NCGraphs extends NCLogger {

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
            $this->_network = "";
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
        }
    }

    /**
     * Helper function return the class id and connector for a classname
     * 
     * @param type $netid
     * @param type $classname
     */
    private function getClassInfo($netid, $classname) {

        // first get class id from the annotation tables        
        $classid = $this->getNameAnnoRootId($netid, $classname);
        if ($classid['anno_status'] == NC_ACTIVE) {
            $classid = $classid['root_id'];
        } else if ($classid['anno_status'] == NC_DEPRECATED) {            
            throw new Exception("Class exists, but is deprecated");
        } else {
            throw new Exception("Name does not match any annotations");
        }

        // get the connector setting from the classes table
        $sql = "SELECT class_id, connector, class_status FROM " . NC_TABLE_CLASSES . " WHERE 
            network_id = ? AND class_id = ?";
        $stmt = prepexec($this->_db, $sql, [$netid, $classid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Invalid class");
        }

        if ($result['class_status'] == NC_DEPRECATED) {
            throw new Exception("Class exists, but is deprecated");
        }
        
        return $result;
    }

    /**
     * Translate between a node name to a node id
     * 
     * @param type $netid
     * @param type $nodename
     */
    private function getNodeId($netid, $nodename) {
        $nodeid = $this->getNameAnnoRootId($netid, $nodename);

        if ($nodeid['anno_status'] == NC_ACTIVE) {
            $nodeid = $nodeid['root_id'];
        } else {
            throw new Exception("Node exists, but is inactive");
        }

        // check that the id is actually in the nodes table
        $sql = "SELECT node_id, node_status FROM " . NC_TABLE_NODES . "
            WHERE network_id = ? AND node_id = ?";
        $stmt = prepexec($this->_db, $sql, [$netid, $nodeid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Invalid node name");
        }
        if ($result['node_status'] != NC_ACTIVE) {
            throw new Exception("Node exists, but is inactive");
        }
        return $nodeid;
    }

    /**
     * Processes a request to create a new node in a network
     * 
     * @return boolean
     * @throws Exception
     */
    public function createNewNode() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["network_name",
            "node_name", "node_title", "class_name"]);

        $netid = $this->getNetworkId($this->_network, true);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfo($netid, $params['class_name']);
        $classid = $classinfo['class_id'];
        if ($classinfo['connector'] != 0) {
            throw new Exception("Invalid class for a node");
        }

        // check if this node name already exists
        $nodename = $this->getNameAnnoRootId($netid, $params['node_name'], false);
        if ($nodename) {
            throw new Exception("Node name already exists");
        }

        // if reached here, create the new node        
        $nodeid = $this->makeRandomID(NC_TABLE_NODES, 'node_id', 'N', NC_ID_LEN);
        $sql = "INSERT INTO " . NC_TABLE_NODES . " 
            (network_id, node_id, class_id, node_status) VALUES (?, ?, ?, ?)";
        $stmt = prepexec($this->_db, $sql, [$netid, $nodeid, $classid, NC_ACTIVE]);

        // log entry for creation
        $this->logActivity($this->_uid, $netid, "created node", $params['node_name'], $params['node_title']);

        // insert name, title, abstract, content, annotations for the link
        $this->insertNewAnnoSet($netid, $this->_uid, $nodeid, $params['node_name'], $params['node_title'], '');

        return $nodeid;
    }

    /**
     * Processes request to create a new link
     * 
     * @return string
     * @throws Exception
     */
    public function createNewLink() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, ["network_name",
            "link_name", "link_title", "class_name", "source_name", "target_name"]);

        $netid = $this->getNetworkId($this->_network, true);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfo($netid, $params['class_name']);
        $classid = $classinfo['class_id'];
        if ($classinfo['connector'] != 1) {
            throw new Exception("Invalid class for a link");
        }

        // get the node id for the source and target
        $sourceid = $this->getNodeId($netid, $params['source_name']);
        $targetid = $this->getNodeId($netid, $params['target_name']);

        // check if the link name is available
        $linkname = $this->getNameAnnoRootId($netid, $params['link_name'], false);
        if ($linkname) {
            throw new Exception("Link name is already taken");
        }

        // if reached here, create the new node        
        $linkid = $this->makeRandomID(NC_TABLE_LINKS, 'link_id', 'L', NC_ID_LEN);
        $sql = "INSERT INTO " . NC_TABLE_LINKS . " 
            (network_id, link_id, source_id, target_id, class_id, link_status) 
              VALUES 
            (?, ?, ?, ?, ?, ?)";
        $stmt = prepexec($this->_db, $sql, [$netid, $linkid, $sourceid, $targetid, $classid, NC_ACTIVE]);

        // log entry for creation
        $this->logActivity($this->_uid, $netid, "created link", $params['link_name'], $params['link_title']);

        // insert name, title, abstract, content, annotations for the link
        $this->insertNewAnnoSet($netid, $this->_uid, $linkid, $params['link_name'], $params['link_title'], '');

        return $linkid;
    }

    /**
     * Fetch all nodes associated with a network
     * 
     * Provides node ids, node names, and class names
     * 
     * @return array
     */
    public function getAllNodes() {

        // shorthand variables        
        $netid = $this->getNetworkId($this->_network, true);

        $tn = "" . NC_TABLE_NODES;
        $tat = "" . NC_TABLE_ANNOTEXT;

        $sql = "SELECT node_id AS id, $tn.class_id AS class_id, 
                       nodenameT.anno_text AS name, classnameT.anno_text AS class_name 
            FROM $tn JOIN $tat AS nodenameT
                        ON $tn.network_id = nodenameT.network_id AND
                        $tn.node_id = nodenameT.root_id
                     JOIN $tat AS classnameT
                        ON $tn.network_id = classnameT.network_id AND
                         $tn.class_id = classnameT.root_id
                  WHERE $tn.network_id = ?                       
                      AND nodenameT.anno_level = " . NC_NAME . "                      
                      AND nodenameT.anno_status = " . NC_ACTIVE . " 
                      AND classnameT.anno_level = " . NC_NAME . "                      
                      AND classnameT.anno_status = " . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Fetch all links associated with a network
     * 
     * Provides link ids, linkages, and link names, and class names
     * 
     * @return array
     */
    public function getAllLinks() {

        // shorthand variables        
        $netid = $this->getNetworkId($this->_network, true);

        $tl = "" . NC_TABLE_LINKS;
        $tat = "" . NC_TABLE_ANNOTEXT;

        $sql = "SELECT link_id AS id, $tl.class_id AS class_id, 
                       source_id AS source, target_id AS target,
                       linknameT.anno_text as name, classnameT.anno_text AS class_name 
            FROM $tl JOIN $tat AS linknameT
                        ON $tl.network_id = linknameT.network_id AND
                        $tl.link_id = linknameT.root_id
                     JOIN $tat AS classnameT
                        ON $tl.network_id = classnameT.network_id AND
                         $tl.class_id = classnameT.root_id
                  WHERE $tl.network_id = ? 
                      AND $tl.link_status = " . NC_ACTIVE . " 
                      AND linknameT.anno_level = " . NC_NAME . "                      
                      AND linknameT.anno_status = " . NC_ACTIVE . " 
                      AND classnameT.anno_level = " . NC_NAME . "                      
                      AND classnameT.anno_status = " . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

}

?>
