<?php

include_once "../helpers/NCTimer.php";
include_once "NCOntology.php";

/*
 * Class handling requests for graph structure (list nodes, add a new node, etc.)
 * 
 */

class NCGraphs extends NCOntology {

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
        $stmt = $this->qPE($sql, [$netid, $classid]);
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
        $params = $this->subsetArray($this->_params, array_merge(["class"], array_keys($this->_annotypes)));

        if (strlen($params['name']) < 2) {
            throw new Exception("Name too short");
        }

        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_NODES, NC_TABLE_ANNOTEXT]);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfo($this->_netid, $params['class']);
        $params['class_id'] = $classinfo['class_id'];
        if ($classinfo['connector'] != 0) {
            throw new Exception("Invalid class for a node");
        }

        // check if this node name already exists        
        $nodeexists = $this->getNameAnnoRootId($this->_netid, $params['name'], false);
        if ($nodeexists) {
            throw new Exception("Node name already exists");
        }

        // if reached here, create the new node (uses batch insert with one node)        
        $nodeid = $this->batchInsertNodes([$params]);

        $this->dbunlock();

        // log entry for creation
        $this->logActivity($this->_uid, $this->_netid, "created node", $params['name'], $params['title']);

        return $nodeid;
    }
   
    /**
     * Perform all db actions associated with inserting nodes.
     * Updates the nodes table, creates name/title/abstract/content annotations
     * 
     * @param array $nodeset
     * 
     * array of new nodes
     * each element should contain 'class_id', 'name', 'title', 'abstract', 'content'     
     * (empty titles, abstract, contents revert to name)
     * 
     * Note the class is a class_id not a class_name
     * 
     * @return string
     * 
     * the first linkid that is inserted (this is useful when inserting a single link)
     * 
     */
    protected function batchInsertNodes($nodeset) {

        //echo "bIN 1 ";
        // get some ids for all the nodes
        $n = count($nodeset);
        $nodeids = $this->makeRandomIDSet(NC_TABLE_NODES, 'node_id', NC_PREFIX_NODE, NC_ID_LEN - 1, $n);

        // Insert into the nodes table using a straight-up query 
        // (not prepped because all items are generated server-side)
        $sql = "INSERT INTO " . NC_TABLE_NODES . " 
            (network_id, node_id, class_id, node_status) VALUES ";
        $sqlvalues = [];
        for ($i = 0; $i < $n; $i++) {
            $temp = [$this->_netid, $nodeids[$i], $nodeset[$i]['class_id'], 1];
            $sqlvalues[] = "('" . implode("', '", $temp) . "')";
        }
        $sql .= implode(", ", $sqlvalues);
        //echo $sql."\n";
        $this->q($sql);
        //echo "done\n";
        //echo "bIN 4 ";
        // insert corresponding to name, title, abstract, content, annotations 
        $this->batchInsertAnnoSets($this->_netid, $nodeset, $nodeids);
        //echo "bIN 5 ";         
        return $nodeids[0];
    }

    /**
     * Processes request to create a new link
     * 
     * @return string
     * @throws Exception
     */
    public function createNewLink() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, array_merge(["class",
                    "source", "target"], array_keys($this->_annotypes)));

        if (strlen($params['name']) < 2) {
            throw new Exception("Name too short");
        }

        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_NODES, NC_TABLE_LINKS, NC_TABLE_ANNOTEXT]);

        // get the class id associated with the class name
        $classinfo = $this->getClassInfoFromName($params['class']);
        $params['class_id'] = $classinfo['class_id'];
        if ($classinfo['connector'] != 1) {
            throw new Exception("Invalid class for a link");
        }

        // check if the link name is available
        $linkinfo = $this->getNameAnnoRootId($this->_netid, $params['name'], false);
        if ($linkinfo) {
            throw new Exception("Link name is already taken");
        }

        // get the node id for the source and target
        $params['source_id'] = $this->getNodeId($this->_netid, $params['source']);
        $params['target_id'] = $this->getNodeId($this->_netid, $params['target']);

        // if reached here, create the new node        
        $linkid = $this->batchInsertLinks([$params]);

        $this->dbunlock();

        // log entry for creation
        $this->logActivity($this->_uid, $this->_netid, "created link", $params['name'], $params['title']);

        return $linkid;
    }

   
    /**
     * Perform all db actions associated with inserting links.
     * Updates the links table, creates name/title/abstract/content annotations
     * 
     * @param array $nodeset
     * 
     * array of new nodes
     * each element should contain 'class_id', 'source_id', 'target_id', 
     * 'name', 'title', 'abstract', 'content'     
     * (empty titles, abstract, contents revert to name)
     * 
     * Note this requires ids (not names) for class, source, target
     * 
     * @return string
     * 
     * the first linkid that is inserted (this is useful when inserting a single link) 
     */
    protected function batchInsertLinks($linkset) {

        // get ids for all the links
        $n = count($linkset);
        $linkids = $this->makeRandomIDSet(NC_TABLE_LINKS, 'link_id', NC_PREFIX_NODE, NC_ID_LEN - 1, $n);

        // Insert into the links table using a straight-up query 
        // (not prepped because all items are generated server-side)
        $sql = "INSERT INTO " . NC_TABLE_LINKS . " 
            (network_id, link_id, source_id, target_id, class_id, link_status) VALUES ";
        $sqlvalues = [];
        for ($i = 0; $i < $n; $i++) {
            $temp = [$this->_netid, $linkids[$i], $linkset[$i]['source_id'],
                $linkset[$i]['target_id'], $linkset[$i]['class_id'], NC_ACTIVE];
            $sqlvalues[] = "('" . implode("', '", $temp) . "')";
        }
        $sql .= implode(", ", $sqlvalues);
        $this->q($sql);

        //echo "bIL 4 ";
        // insert corresponding to name, title, abstract, content, annotations 
        $this->batchInsertAnnoSets($this->_netid, $linkset, $linkids);

        return $linkids[0];
        //echo "bIL 5 ";  
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
                       nodenameT.anno_text AS name, classnameT.anno_text AS class 
            FROM $tn JOIN $tat AS nodenameT
                        ON $tn.network_id = nodenameT.network_id AND
                        $tn.node_id = nodenameT.root_id
                     JOIN $tat AS classnameT
                        ON $tn.network_id = classnameT.network_id AND
                         $tn.class_id = classnameT.root_id
                  WHERE $tn.network_id = ?                       
                      AND nodenameT.anno_type = " . NC_NAME . "                      
                      AND nodenameT.anno_status = " . NC_ACTIVE . " 
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
                       linknameT.anno_text as name, classnameT.anno_text AS class 
            FROM $tl JOIN $tat AS linknameT
                        ON $tl.network_id = linknameT.network_id AND
                        $tl.link_id = linknameT.root_id
                     JOIN $tat AS classnameT
                        ON $tl.network_id = classnameT.network_id AND
                         $tl.class_id = classnameT.root_id
                  WHERE $tl.network_id = ? 
                      AND $tl.link_status = " . NC_ACTIVE . " 
                      AND linknameT.anno_type = " . NC_NAME . "                      
                      AND linknameT.anno_status = " . NC_ACTIVE . " 
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
