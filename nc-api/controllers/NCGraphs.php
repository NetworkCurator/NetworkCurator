<?php

include_once "NCOntology.php";
include_once "../helpers/NCTimer.php";
/*
 * Class handling requests for graph structure (list nodes, add a new node, etc.)
 * 
 */

class NCGraphs extends NCOntology {
    // db connection and array of parameters are inherited from NCLogger and NCOntology

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
     * Translate between a node name to a node id
     * 
     * @param type $netid
     * @param type $nodename
     */
    private function getNodeId($netid, $nodename) {

        $nodeid = $this->getNameAnnoRootId($netid, $nodename, NC_NODE);
        if ($nodeid['anno_status'] == NC_ACTIVE) {
            $nodeid = $nodeid['root_id'];
        } else {
            throw new Exception("Node exists, but is inactive");
        }

        // check that the id is actually in the nodes table
        $sql = "SELECT node_id, node_status FROM " . NC_TABLE_NODES . "
            WHERE network_id = ? AND node_id = ?";
        $stmt = $this->qPE($sql, [$netid, $nodeid]);
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
        $params = $this->subsetArray($this->_params, ["node_name", "node_title", "class_name"]);

        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_NODES, NC_TABLE_ANNOTEXT]);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfoFromName($params['class_name']);
        $classid = $classinfo['class_id'];
        if ($classinfo['connector'] != 0) {
            throw new Exception("Invalid class for a node");
        }

        // check if this node name already exists        
        $nodename = $this->getNameAnnoRootId($this->_netid, $params['node_name'], NC_NODE, false);
        if ($nodename) {
            throw new Exception("Node name already exists");
        }

        // if reached here, create the new node  
        $nodeid = $this->insertNode($params['node_name'], $classid, $params['node_title']);

        $this->dbunlock();

        // log entry for creation
        $this->logActivity($this->_uname, $this->_netid, "created node", $params['node_name'], $params['node_title']);

        return $nodeid;
    }

    /**
     * Internal function that perform data insert for a valid node.
     * 
     * 
     * The inputs go straight into the db, without any checks.
     * 
     * @param type $nodename
     * @param type $nodetitle
     * @param type $nodeabstract
     * @param type $nodecontent
     * 
     * @return type
     */
    protected function insertNode($nodename, $classid, $nodetitle, $nodeabstract = 'empty', $nodecontent = 'empty') {

        $timer = new NCTimer();
        $timer->recordTime("start");
        $sql = "INSERT INTO " . NC_TABLE_NODES . " 
                    (network_id, class_id, node_status) VALUES (?, ?, ?)";
        $this->qPE($sql, [$this->_netid, $classid, NC_ACTIVE]);        
        $nodeid = $this->lID();
$timer->recordTime("nodeid");
        // insert name, title, abstract, content, annotations for the link
        $this->insertNewAnnoSet($this->_netid, $this->_uid, $nodeid, NC_NODE, $nodename, $nodetitle, $nodeabstract, $nodecontent);
$timer->recordTime("end");
//echo $timer->showTimes();
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
        $params = $this->subsetArray($this->_params, ["link_name", "link_title",
            "class_name", "source_name", "target_name"]);

        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_NODES, NC_TABLE_LINKS, NC_TABLE_ANNOTEXT]);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfoFromName($params['class_name']);
        $classid = $classinfo['class_id'];
        if ($classinfo['connector'] != 1) {
            throw new Exception("Invalid class for a link");
        }

        // get the node id for the source and target
        $sourceid = $this->getNodeId($this->_netid, $params['source_name']);
        $targetid = $this->getNodeId($this->_netid, $params['target_name']);

        // check if the link name is available
        $linkname = $this->getNameAnnoRootId($this->_netid, $params['link_name'], NC_LINK, false);
        if ($linkname) {
            throw new Exception("Link name is already taken");
        }

        // if reached here, create the new node        
        $linkid = $this->insertLink($params['link_name'], $classid, $sourceid, $targetid, $params['link_title']);

        $this->dbunlock();

        // log entry for creation
        $this->logActivity($this->_uid, $this->_netid, "created link", $params['link_name'], $params['link_title']);

        return $linkid;
    }

    /**
     * Internal function that perform data insert for a valid link.
     * 
     * The function requires a prepped query, so make sure to call
     * prepInsertLink() before using this function.
     * 
     * The inputs go straight into the db, without any checks.
     * 
     * @param type $linkname
     * @param type $classid
     * @param type $sourceid
     * @param type $targetid
     * @param type $linkname
     * @param type $linktitle
     * @param type $linkabstract
     * @param type $linkcontent
     * @return string
     * 
     * id for the new link
     */
    protected function insertLink($linkname, $classid, $sourceid, $targetid, $linktitle, $linkabstract = 'empty', $linkcontent = 'empty') {

        // insert new link data
        $sql = "INSERT INTO " . NC_TABLE_LINKS . " 
            (network_id, source_id, target_id, class_id, link_status) 
              VALUES (?, ?, ?, ?, ?)";
        $this->qPE($sql, [$this->_netid, $sourceid, $targetid, $classid, NC_ACTIVE]);
        $linkid = $this->lID();

        // insert name, title, abstract, content, annotations for the link
        $this->insertNewAnnoSet($this->_netid, $this->_uid, $linkid, NC_LINK, $linkname, $linktitle, $linkabstract, $linkcontent);

        return $linkid;
    }

    /**
     * Fetch all nodes associated with a network
     * 
     * Provides node ids, node names, and class names
     * 
     * @param boolean $namekeys
     * 
     * when true, the output is indexed by the node name (Nxxxxxx)
     * when false, there are not named indexes 
     * 
     * @return array
     */
    public function getAllNodes($namekeys = false) {

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
                      AND nodenameT.root_type = " . NC_NODE . "                      
                      AND nodenameT.anno_type = " . NC_NAME . "                      
                      AND nodenameT.anno_status = " . NC_ACTIVE . " 
                      AND classnameT.root_type = " . NC_CLASS . "    
                      AND classnameT.anno_type = " . NC_NAME . "                      
                      AND classnameT.anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = array();
        if ($namekeys) {
            while ($row = $stmt->fetch()) {
                $result[$row['name']] = $row;
            }
        } else {
            while ($row = $stmt->fetch()) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * Fetch all links associated with a network
     * 
     * Provides link ids, linkages, and link names, and class names
     *
     * @param boolean namekeys
     * 
     * @return array
     */
    public function getAllLinks($namekeys = false) {

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
                      AND linknameT.root_type = " . NC_LINK . "                                            
                      AND linknameT.anno_type = " . NC_NAME . "                      
                      AND linknameT.anno_status = " . NC_ACTIVE . " 
                      AND classnameT.root_type = " . NC_CLASS . "
                      AND classnameT.anno_type = " . NC_NAME . "                      
                      AND classnameT.anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = array();
        if ($namekeys) {
            while ($row = $stmt->fetch()) {
                $result[$row['name']] = $row;
            }
        } else {
            while ($row = $stmt->fetch()) {
                $result[] = $row;
            }
        }
        return $result;
    }

}

?>
