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
        $params['status'] = NC_ACTIVE;
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

        $newstatus = $this->standardizeStatus($newstatus, "AD");

        $this->dblock([NC_TABLE_NODES, NC_TABLE_ANNOTEXT]);

        // check if this node name exists        
        $nodeinfo = $this->getNameAnnoRootId($this->_netid, $nodename, true);
        $nodeid = $nodeinfo['root_id'];

        // set the node status in the nodes table        
        $this->batchSetStatus('node', [$nodeid], $newstatus);

        $this->dbunlock();

        // log entry 
        $logmsg = "activated node";
        if ($newstatus == NC_DEPRECATED || $newstatus == NC_OLD) {
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
            $nowstatus = $this->standardizeStatus($nodebatch[$i]['status'], "AD");
            $temp = [$this->_netid, $nodeids[$i],
                $nodebatch[$i]['class_id'], $nowstatus];
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
        $params['status'] = NC_ACTIVE;
        $linkid = $this->batchInsertLinks([$params]);

        $this->dbunlock();

        // log entry for creation
        $this->logActivity($this->_uid, $this->_netid, "created link", $params['name'], $params['title']);

        return $linkid;
    }

    /**
     * Helper sets several db entries to a common status code
     * 
     * @param string $what
     * 
     * one of "class", "link" or "node"
     * 
     * @param array $idarray
     * 
     * array of several id codes to toggle
     *      
     * @param int $newstatus
     * 
     * new status
     * 
     */
    protected function batchSetStatus($what, $idarray, $newstatus) {

        if (count($idarray) == 0) {
            return;
        }

        // find the table that should be affected
        $tablename = $this->getTableName($what);

        // prepare and exec statement with multiple updates at once
        $sql = "UPDATE $tablename SET " . $what . "_status = " . $newstatus . " WHERE 
                     network_id = :netid AND (";
        $params = ['netid' => $this->_netid];
        $sqlcheck = [];
        $n = count($idarray);
        for ($i = 0; $i < $n; $i++) {
            $x = sprintf("%'.06d", $i);
            $sqlcheck[] = " " . $what . "_id = :x_$x ";
            $params["x_$x"] = $idarray[$i];
        }
        $sql .= implode("OR", $sqlcheck) . ")";
        $this->qPE($sql, $params);
    }

    /**
     * Helper sets several db entries to a common class_id.
     * Follows similar structure as batchSetStatus.
     * 
     * @param type $what
     * 
     * either "node" or "link"
     * 
     * @param type $idarray
     * 
     * array with node_id or link_id to change into the new class code
     * 
     * @param type $newclassid
     * 
     * new class code to assign to the objects in $idarray
     * 
     */
    protected function batchSetClass($what, $idarray, $newclassid) {

        if (count($idarray) == 0) {
            return;
        }

        // find the table that should be affected
        $tablename = $this->getTableName($what);

        // prepare and exec statement with multiple updates at once
        $sql = "UPDATE $tablename SET class_id = '" . $newclassid . "' WHERE 
                     network_id = :netid AND (";
        $params = ['netid' => $this->_netid];
        $sqlcheck = [];
        $n = count($idarray);
        for ($i = 0; $i < $n; $i++) {
            $x = sprintf("%'.06d", $i);
            $sqlcheck[] = " " . $what . "_id = :x_$x ";
            $params["x_$x"] = $idarray[$i];
        }
        $sql .= implode("OR", $sqlcheck) . ")";
        $this->qPE($sql, $params);
    }

    /**
     * Helper function, adjust the status of a named link
     * 
     * @param type $linkid
     * @param type $newstatus
     * 
     * @return type
     * 
     * id code for the link
     */
    private function toggleLink($linkname, $newstatus) {

        $newstatus = $this->standardizeStatus($newstatus, "AD");

        $this->dblock([NC_TABLE_LINKS, NC_TABLE_ANNOTEXT]);

        // check if this link name exists        
        $linkinfo = $this->getNameAnnoRootId($this->_netid, $linkname, true);
        $linkid = $linkinfo['root_id'];

        // set the link status in the links table
        $this->batchSetStatus('link', [$linkid], $newstatus);

        $this->dbunlock();

        // log entry 
        $logmsg = "activated link";
        if ($newstatus == NC_DEPRECATED || $newstatus == NC_OLD) {
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
            $nowstatus = $this->standardizeStatus($linkbatch[$i]['status'], "AD");
            $temp = [$this->_netid, $linkids[$i], $linkbatch[$i]['source_id'],
                $linkbatch[$i]['target_id'], $linkbatch[$i]['class_id'], $nowstatus];
            $sqlvalues[] = "('" . implode("', '", $temp) . "')";
        }
        $sql .= implode(", ", $sqlvalues);
        $this->q($sql);

        // insert corresponding to name, title, abstract, content, annotations 
        $this->batchInsertAnnoSets($this->_netid, $linkbatch, $linkids);

        return $linkids[0];
    }

    /**
     * Helper function creates a bit of sql code used in getAllNodesExtended and getAllLinksExtended
     */
    private function getGetExtendedHelpers() {

        // column names
        $tac = "anno_type";
        $tat = "anno_text";
        // build helper sql bits first in array
        $sqlcase = [];
        $sqlgroup = [];
        $carray = $this->_annotypes;
        $atypes = array_keys($carray);
        foreach ($atypes AS $what) {
            $sqlcase[] = "(CASE WHEN $tac = $carray[$what] THEN $tat ELSE '' END) AS $what";
            $sqlgroup[] = "GROUP_CONCAT($what SEPARATOR '') AS " . $what . "";
        }
        $sqlcase = implode(", ", $sqlcase);
        $sqlgroup = implode(", ", $sqlgroup);

        return ['case' => $sqlcase, 'group' => $sqlgroup];
    }

    /**
     * Fetch all nodes associated with a network, with name, title, abstract, content
     * 
     * (In contrast to getAllNodes, this does not provide class names)
     * (It is a separate function as it uses a different database query strategy)
     * 
     * @param boolean $namekeys
     * 
     * when true, the output is indexed by the node name (Nxxxxxx)
     * when false, there are not named indexes 
     *      
     * @return array
     */
    protected function getAllNodesExtended() {

        $tn = "" . NC_TABLE_NODES;
        $ta = "" . NC_TABLE_ANNOTEXT;

        // get sql helper bits
        $sqlhelper = $this->getGetExtendedHelpers();

        $innersql = "SELECT node_id AS id, $tn.class_id AS class_id, 
                       anno_text, anno_type, node_status AS status, " . $sqlhelper['case'] . "
            FROM $tn JOIN $ta 
                        ON $tn.network_id = $ta.network_id AND
                        $tn.node_id = $ta.root_id                     
                  WHERE $tn.network_id = ?                          
                      AND $ta.anno_type <= " . NC_CONTENT . "
                      AND $ta.anno_status = " . NC_ACTIVE . "                       
                  GROUP BY $ta.root_id, $ta.anno_type";
        $sql = "SELECT id, class_id, status, " . $sqlhelper['group'] . " FROM ($innersql) AS T GROUP BY id ";
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * This is the original version that is working
     * 
     * @param type $namekeys
     * @return type
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
     * Fetch all links associated with a network, with name, title, abstract, content.
     * Analogous to getAllNodesExtended
     * 
     * (In contrast to getAllLinks, this does not provide class names)
     * (It is a separate function as it uses a different database query strategy)
     * 
     * @param boolean $namekeys
     * 
     * when true, the output is indexed by the node name (Nxxxxxx)
     * when false, there are not named indexes 
     *      
     * @return array
     */
    protected function getAllLinksExtended() {

        $tl = "" . NC_TABLE_LINKS;
        $ta = "" . NC_TABLE_ANNOTEXT;

        // get sql helper bits
        $sqlhelper = $this->getGetExtendedHelpers();

        $innersql = "SELECT link_id AS id, $tl.class_id AS class_id, source_id, target_id, 
                       anno_text, anno_type, link_status AS status, " . $sqlhelper['case'] . "
            FROM $tl JOIN $ta 
                        ON $tl.network_id = $ta.network_id AND
                        $tl.link_id = $ta.root_id                     
                  WHERE $tl.network_id = ?                          
                      AND $ta.anno_type <= " . NC_CONTENT . "
                      AND $ta.anno_status = " . NC_ACTIVE . "                       
                  GROUP BY $ta.root_id, $ta.anno_type";
        $sql = "SELECT id, class_id, status, source_id, target_id, "
                . $sqlhelper['group'] . " FROM ($innersql) AS T GROUP BY id ";
        $stmt = $this->qPE($sql, [$this->_netid]);
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
     * @param boolean namekeys
     *
     * @param boolean $fulldetail
     * 
     * when true, the output contains name, title, abstract, content
     * when false, the output contains only name and title
     * 
     * @return array
     */
    public function getAllLinks($namekeys = false, $fulldetail = false) {

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
     * accepted parameters are target (node name) and linkclass (ontology class name of 
     * connecting link)
     * 
     * link class names can be separated by commas
     * 
     * @return array
     * 
     */
    public function getNeighbors() {

        $params = $this->subsetArray($this->_params, ["query", "linkclass"]);

        // get ids for the query node 
        $queryid = $this->getNameAnnoRootId($this->_netid, $params['query']);
        $queryid = $queryid['root_id'];

        // get ids for the link types (can be array of types separated by commas)
        $linkclassa = explode(",", $params['linkclass']);
        $linkclassid = [];
        for ($i = 0; $i < sizeof($linkclassa); $i++) {
            $temp = $this->getNameAnnoRootId($this->_netid, $linkclassa[$i]);
            $linkclassid[] = $temp['root_id'];
        }

        // build query fetching node ids connected to target node
        $tl = "" . NC_TABLE_LINKS;
        $tat = "" . NC_TABLE_ANNOTEXT;

        $neighbors = array();

        // prep parts of a sql statement (fetching node names that are adjacent to the query)
        $sqlbase = "SELECT anno_text AS name                       
            FROM $tl JOIN $tat ON $tl.network_id = $tat.network_id ";
        $sqlwhere = " WHERE $tl.network_id = :network_id                       
                      AND $tl.link_status = " . NC_ACTIVE . "
                      AND $tat.anno_type = " . NC_NAME . "                      
                      AND $tat.anno_status = " . NC_ACTIVE . "";
        $sqlclass = "AND (";
        for ($i = 0; $i < sizeof($linkclassid); $i++) {
            if ($i > 0) {
                $sqlclass .= " OR ";
            }
            $sqlclass.= " $tl.class_id = :class_$i ";
        }
        $sqlclass .= ");";

        // preparey array for the sql PDO query
        $pt = ['network_id' => $this->_netid, 'query_id' => $queryid];
        for ($i = 0; $i < sizeof($linkclassid); $i++) {
            $pt["class_$i"] = $linkclassid[$i];
        }

        // fetch neighbors when params[query] is the source_id
        $sql = $sqlbase . " AND $tl.target_id = $tat.root_id " . $sqlwhere . " AND $tl.source_id = :query_id " . $sqlclass;
        $stmt = $this->qPE($sql, $pt);
        while ($row = $stmt->fetch()) {
            $neighbors[] = $row['name'];
        }

        // fetch neighbors when params[query] is the target
        $sql = $sqlbase . " AND $tl.source_id = $tat.root_id " . $sqlwhere . " AND $tl.target_id = :query_id " . $sqlclass;
        $stmt = $this->qPE($sql, $pt);
        while ($row = $stmt->fetch()) {
            $neighbors[] = $row['name'];
        }

        return $neighbors;
    }

    /**
     * fetch the class_id and class name associated with a node or link
     * 
     */
    public function getObjectClass() {

        $params = $this->subsetArray($this->_params, ["query"]);

        // check if this queryid is a node or link, fetch the class_id
        $result = [];
        $sql = "SELECT class_id FROM " . NC_TABLE_NODES . " WHERE network_id = ? AND node_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $params['query']]);
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }
        $sql = "SELECT class_id FROM " . NC_TABLE_LINKS . " WHERE network_id = ? AND link_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $params['query']]);
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        if (count($result) == 0) {
            return "";
        } else if (count($result) > 1) {
            throw new Exception("Invalid object response");
        }

        // get name of the class
        $classname = $this->getObjectName($this->_netid, $result[0]['class_id'], true);
        return $classname['anno_text'];
    }

    /**
     * looks up an object id in various tables of the db to check what type of
     * object it is (node, link, or class), e.g. converts "Labc01234" into "link"
     * 
     * @param string $objid
     * 
     * @return string 
     * 
     * a string "node" or "link" or "class"
     * 
     */
    protected function isNodeOrLinkOrClass($objid) {

        $sql = "SELECT class_id FROM " . NC_TABLE_NODES . " WHERE network_id = ? AND node_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $objid]);
        if ($stmt->fetch()) {
            return "node";
        }

        $sql = "SELECT class_id FROM " . NC_TABLE_LINKS . " WHERE network_id = ? AND link_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $objid]);
        if ($stmt->fetch()) {
            return "link";
        }

        $sql = "SELECT class_id FROM " . NC_TABLE_CLASSES . " WHERE network_id = ? AND class_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $objid]);
        if ($stmt->fetch()) {
            return "class";
        }

        // if reached here, its an invalid object
        throw new Exception("Object id does not exist in database");
    }

    /**
     * set a new class for a node or linke
     * 
     * @return int
     * 
     * 1 if the update went alright
     * 
     */
    public function updateClass() {

        $params = $this->subsetArray($this->_params, ['target_id', 'class']);
               
        // check if object exists and class name exists
        $objtype = $this->isNodeOrLinkOrClass($params['target_id']);
        $classid = $this->getNameAnnoRootId($this->_netid, $params['class'])['root_id'];

        $tabname = NC_TABLE_LINKS;
        if ($objtype == "node") {
            $tabname = NC_TABLE_NODES;
        } else if ($objtype == "link") {
            $tabname = NC_TABLE_LINKS;
        } else {
            throw new Exception("Cannot update the class");
        }

        // check that the object does not already have that class_id
        $sql = "SELECT class_id FROM $tabname WHERE network_id = ? and ".$objtype."_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $params['target_id']]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new Exception("Error reading from db");
        }
        if ($row['class_id'] == $classid) {
            throw new Exception("Cannot update class (same as existing class)");
        }
        
        // update the database table
        $sql = "UPDATE $tabname SET class_id = ? WHERE network_id = ? AND " . $objtype . "_id = ? ";
        $this->qPE($sql, [$classid, $this->_netid, $params['target_id']]);

        // log the event        
        $this->logActivity($this->_uid, $this->_netid, "updated class for $objtype ", $params['target_id'], $params['class']);
        
        return 1;
    }

    /**
     * set a new owner for annotation pertaining to a node or link
     * 
     * @return int
     * 
     * 1 if all goes well. 
     */
    public function updateOwner() {
        
        $params = $this->subsetArray($this->_params, ['target_id', 'owner']);
        
        // check if object exists and class name exists
        $objtype = $this->isNodeOrLinkOrClass($params['target_id']);        
        $ownerperm = $this->getUserPermissions($this->_netid, $params['owner']);        
        if ($ownerperm < NC_PERM_EDIT) {
            throw new Exception("New owner does not exist or does not have sufficient permissions.");
        }
        
        // fetch all the annotations that belong to the target_id
        $sql = "SELECT datetime, anno_id, anno_type, owner_id, user_id, network_id, 
                       root_id, parent_id, anno_text 
                       FROM ".NC_TABLE_ANNOTEXT." WHERE network_id= ?  and root_id = ?";
        $stmt = $this->qPE($sql, [$this->_netid, $params['target_id']]);
        $result = [];
        while ($row = $stmt->fetch()) {
            // change the owner field here
            $row['owner_id'] = $params['owner'];            
            $result[] = $row;
        }
        
        // re-send the annotation to the db. This will update the owner_id
        $this->batchUpdateAnno($result);        
             
        // log the event        
        $this->logActivity($this->_uid, $this->_netid, "updated ownership for $objtype ", $params['target_id'], $params['owner']);
        
        return 1;
    }

}

?>
