<?php

include_once dirname(__FILE__) . "/../helpers/NCTimer.php";
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
        $classinfo = $this->getClassInfo($params['class']);
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
     * Helper function to set the node_status to either NC_ACTIVE or NC_DEPRECATED
     * 
     * @param string $nodename
     * @param integer $newstatus
     * @return string
     * 
     * id code for named node
     * 
     */
    private function toggleNode($nodename, $newstatus) {

        $this->dblock([NC_TABLE_NODES, NC_TABLE_ANNOTEXT]);

        // check if this node name exists        
        $nodeinfo = $this->getNameAnnoRootId($this->_netid, $nodename, true);
        $nodeid = $nodeinfo['root_id'];

        // set the node as inactive in the nodes table        
        $sql = "UPDATE " . NC_TABLE_NODES . " SET node_status = " . $newstatus . " WHERE 
                     network_id = ? AND node_id = ? ";
        $this->qPE($sql, [$this->_netid, $nodeid]);

        $this->dbunlock();

        // log entry 
        $logmsg = "activated node";
        if ($newstatus == NC_DEPRECATED) {
            $logmsg = "removed node";
        }
        $this->logActivity($this->_uid, $this->_netid, $logmsg, $nodename, $nodeid);

        return $nodeid;
    }

    /**
     *  Sets the status of a named node to deprecated
     * 
     * @return string
     * 
     * id of deprecated node
     * 
     */
    public function removeNode() {
        $params = $this->subsetArray($this->_params, ["name"]);
        return $this->toggleNode($params['name'], NC_DEPRECATED);
    }

    /**
     * Sets the status of a named node to active
     * 
     * @return string
     * 
     * id of activated node
     * 
     */
    public function activateNode() {
        $params = $this->subsetArray($this->_params, ["name"]);
        return $this->toggleNode($params['name'], NC_ACTIVE);
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
    protected function batchInsertNodes($nodebatch) {

        // get some ids for all the nodes
        $n = count($nodebatch);
        $nodeids = $this->makeRandomIDSet(NC_TABLE_NODES, 'node_id', NC_PREFIX_NODE, NC_ID_LEN, $n);

        // Insert into the nodes table using a straight-up query 
        // (not prepped because all items are generated server-side)
        $sql = "INSERT INTO " . NC_TABLE_NODES . " 
            (network_id, node_id, class_id, node_status) VALUES ";
        $sqlvalues = [];
        for ($i = 0; $i < $n; $i++) {
            $temp = [$this->_netid, $nodeids[$i], $nodebatch[$i]['class_id'], 1];
            $sqlvalues[] = "('" . implode("', '", $temp) . "')";
        }
        $sql .= implode(", ", $sqlvalues);
        $this->q($sql);

        // insert corresponding to name, title, abstract, content, annotations 
        $this->batchInsertAnnoSets($this->_netid, $nodebatch, $nodeids);

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
        $classinfo = $this->getClassInfo($params['class']);
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
     * Helper function, adjust the status of a named link
     * 
     * @param type $linkname
     * @param type $newstatus
     * 
     * @return type
     * 
     * id code for the link
     */
    private function toggleLink($linkname, $newstatus) {

        $this->dblock([NC_TABLE_LINKS, NC_TABLE_ANNOTEXT]);

        // check if this link name exists        
        $linkinfo = $this->getNameAnnoRootId($this->_netid, $linkname, true);
        $linkid = $linkinfo['root_id'];

        // set the link as inactive in the links table
        $sql = "UPDATE " . NC_TABLE_LINKS . " SET link_status = " . $newstatus . " WHERE 
                     network_id = ? AND link_id = ? ";
        $this->qPE($sql, [$this->_netid, $linkid]);

        $this->dbunlock();

        // log entry 
        $logmsg = "activated link";
        if ($newstatus == NC_DEPRECATED) {
            $logmsg = "removed link";
        }
        $this->logActivity($this->_uid, $this->_netid, $logmsg, $linkname, $linkid);

        return $linkid;
    }

    /**
     * Sets the status of a named link to deprecated
     * 
     * @return string
     * 
     * id of deprecated link
     * 
     */
    public function removeLink() {
        $params = $this->subsetArray($this->_params, ["name"]);
        return $this->toggleLink($params['name'], NC_DEPRECATED);
    }

    /**
     * Sets the status of a named link to active
     * 
     * @return string
     * 
     * id of deprecated link
     * 
     */
    public function activateLink() {
        $params = $this->subsetArray($this->_params, ["name"]);
        return $this->toggleLink($params['name'], NC_ACTIVE);
    }

    /**
     * Perform all db actions associated with inserting links.
     * Updates the links table, creates name/title/abstract/content annotations
     * 
     * @param array $linkbatch
     * 
     * array of new links
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
    protected function batchInsertLinks($linkbatch) {

        // get ids for all the links
        $n = count($linkbatch);
        $linkids = $this->makeRandomIDSet(NC_TABLE_LINKS, 'link_id', NC_PREFIX_LINK, NC_ID_LEN, $n);

        // Insert into the links table using a straight-up query 
        // (not prepped because all items are generated server-side)
        $sql = "INSERT INTO " . NC_TABLE_LINKS . " 
            (network_id, link_id, source_id, target_id, class_id, link_status) VALUES ";
        $sqlvalues = [];
        for ($i = 0; $i < $n; $i++) {
            $temp = [$this->_netid, $linkids[$i], $linkbatch[$i]['source_id'],
                $linkbatch[$i]['target_id'], $linkbatch[$i]['class_id'], NC_ACTIVE];
            $sqlvalues[] = "('" . implode("', '", $temp) . "')";
        }
        $sql .= implode(", ", $sqlvalues);
        $this->q($sql);

        // insert corresponding to name, title, abstract, content, annotations 
        $this->batchInsertAnnoSets($this->_netid, $linkbatch, $linkids);

        return $linkids[0];
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
                       nodenameT.anno_text AS name, classnameT.anno_text AS class,
                       node_status AS status
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
                       linknameT.anno_text AS name, classnameT.anno_text AS class,
                       link_status AS status
            FROM $tl JOIN $tat AS linknameT
                        ON $tl.network_id = linknameT.network_id AND
                        $tl.link_id = linknameT.root_id
                     JOIN $tat AS classnameT
                        ON $tl.network_id = classnameT.network_id AND
                         $tl.class_id = classnameT.root_id
                  WHERE $tl.network_id = ?                       
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

    /**
     * Fetch nodes that are immediate neighbors of a given node
     * 
     * @return array
     * 
     */
    public function getNeighbors() {

        //return json_encode($this->_params);
        $params = $this->subsetArray($this->_params, ["target", "linkclass"]);

        // get ids for the target node and for the link type
        $nodeid = $this->getNameAnnoRootId($this->_netid, $params['target']);
        $linkclassid = $this->getNameAnnoRootId($this->_netid, $params['linkclass']);
        $nodeid = $nodeid['root_id'];
        $linkclassid = $linkclassid['root_id'];

        // build query fetching node ids connected to target node
        $tl = "" . NC_TABLE_LINKS;
        $tat = "" . NC_TABLE_ANNOTEXT;

        $neighbors = array();

        // prep parts of a sql statement
        $sqlbase = "SELECT anno_text AS name                       
            FROM $tl JOIN $tat ON $tl.network_id = $tat.network_id ";
        $sqlwhere = " WHERE $tl.network_id = ?                       
                      AND $tl.link_status = " . NC_ACTIVE . "
                      AND $tat.anno_type = " . NC_NAME . "                      
                      AND $tat.anno_status = " . NC_ACTIVE . "                       
                      AND $tl.class_id = ?";

        // fetch neighbors when params[target] is the source_id
        $sql = $sqlbase . " AND $tl.target_id = $tat.root_id " . $sqlwhere . " AND $tl.source_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid, $linkclassid, $nodeid]);
        while ($row = $stmt->fetch()) {            
            $neighbors[] = $row['name'];
        }       

        // fetch neighbors when params[target] is the target
        $sql = $sqlbase . " AND $tl.source_id = $tat.root_id " . $sqlwhere . " AND $tl.target_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid, $linkclassid, $nodeid]);
        while ($row = $stmt->fetch()) {
            $neighbors[] = $row['name'];
        }

        return $neighbors;
    }

}

?>
