<?php

/**
 * Class handling requests for node and link classes
 * (e.g. define a new class of node or link)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCOntology extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger        
    // some variables extracted from $_params, for convenience
    protected $_network;
    protected $_netid;
    protected $_uperm;
    // default svg defs for nodes and links (for nodes, in two parts, id="" will be inserted in the middle
    protected $_def_node = ['<circle', 'cx=0 cy=0 r=9 />'];
    protected $_def_link = '<style type="text/css"></style>';

    /**
     * Constructor 
     * 
     * @param PDO $db
     * 
     * Connection to the NC database
     * 
     * @param array $params
     * 
     * array with parameters
     */
    public function __construct($db, $params) {

        if (isset($params['network'])) {
            $this->_network = $params['network'];
        } else {
            throw new Exception("Missing required parameter network");
        }
        unset($params['network']);

        parent::__construct($db, $params);

        // all functions will need to know the network id code
        $this->_netid = $this->getNetworkId($this->_network, true);

        // check that the user has permissions to view this network   
        $this->_uperm = $this->getUserPermissions($this->_netid, $this->_uid);
        if ($this->_uperm < NC_PERM_VIEW) {
            throw new Exception("Insufficient permissions to query ontology");
        }
    }

    /**
     * Get a mapping between class_name and class_id
     *    
     * (This is set to public for testing, but is not useful for the end user)
     *   
     */
    public function getOntologyDictionary($what = "both") {
        $tc = "" . NC_TABLE_CLASSES;
        $ta = "" . NC_TABLE_ANNOTEXT;

        $subtype = "";
        if ($what == "node") {
            $subtype = " AND $tc.connector = 0 ";
        } else if ($what == "link") {
            $subtype = " AND $tc.connector = 1 ";
        }

        $sql = "SELECT class_id, anno_text as class_name 
           FROM $tc JOIN $ta ON $tc.class_id = $ta.root_id AND $tc.network_id = $ta.network_id
             WHERE $tc.network_id = ? AND $ta.network_id = ? $subtype
                   AND anno_type = " . NC_NAME . " AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $this->_netid]);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['class_id']] = $row['class_name'];
        }

        return $result;
    }

    /**
     * Similar to listOntology with parameter ontology='nodes'
     */
    public function getNodeOntology($idkeys = true, $fulldetail = false) {
        $this->_params['ontology'] = 'nodes';
        return $this->getOntology($idkeys, $fulldetail);
    }

    /**
     * Similar to listOntology with parameter ontology='links'
     */
    public function getLinkOntology($idkeys = true, $fulldetail = false) {
        $this->_params['ontology'] = 'links';
        return $this->getOntology($idkeys, $fulldetail);
    }

    /**
     * Looks all the classes associated with nodes or links in a network
     * 
     * This function needs work - it is very messy.
     * 
     * @param logical idkeys
     * 
     * set true to get an array indexed by class_id. set false for indexes by class_name
     * 
     * @return array of classes
     * 
     * @throws Exception
     * 
     */
    public function getOntology($idkeys = true, $fulldetail = false) {

        // check that required parameters are defined
        $onto = $this->subsetArray($this->_params, ["ontology"])["ontology"];
        if ($onto == "links") {
            $onto = " AND connector=1 ";
        } else if ($onto == "nodes") {
            $onto = " AND connector=0 ";
        } else {
            $onto = "";
        }

        // tables used
        $tc = "" . NC_TABLE_CLASSES;
        $ta = "" . NC_TABLE_ANNOTEXT;
        // columns used
        $tac = $ta . ".anno_type";
        $tat = $ta . ".anno_text";
        $tai = $ta . ".anno_id";
        $tad = $ta . ".datetime";
        $tao = $ta . ".owner_id";

        // prepare some helper sql bits
        $sqlcase = [];
        $sqlgroup = [];
        $carray = array_merge($this->_annotypes, ['defs' => NC_DEFS]);
        $atypes = array_keys($carray);
        if (!$fulldetail) {
            $atypes = ['name', 'defs'];
        }
        foreach ($atypes AS $what) {
            $sqlcase[] = "(CASE WHEN $tac = $carray[$what] THEN $tat ELSE '' END) AS $what";
            $sqlcase[] = "(CASE WHEN $tac = $carray[$what] THEN $tai ELSE '' END) AS " . $what . "_anno_id";
            $sqlgroup[] = "GROUP_CONCAT($what SEPARATOR '') AS " . $what . "";
            $sqlgroup[] = "GROUP_CONCAT($what" . "_anno_id SEPARATOR '') AS " . $what . "_anno_id";
            if ($fulldetail) {
                $sqlcase[] = "(CASE WHEN $tac = $carray[$what] THEN $tad ELSE '' END) AS " . $what . "_datetime";
                $sqlcase[] = "(CASE WHEN $tac = $carray[$what] THEN $tao ELSE '' END) AS " . $what . "_owner_id";
                $sqlgroup[] = "GROUP_CONCAT($what" . "_datetime SEPARATOR '') AS " . $what . "_datetime";
                $sqlgroup[] = "GROUP_CONCAT($what" . "_owner_id SEPARATOR '') AS " . $what . "_owner_id";
            }
        }
        $sqlcase = implode(", ", $sqlcase);
        $sqlgroup = implode(", ", $sqlgroup);

        // query the classes table for this network:
        // pivot annotation types to get rows with names, titles, abstracts, and content
        $innersql = "SELECT class_id, $tc.parent_id AS parent_id, connector, 
            directional, class_status, $sqlcase 
            FROM $tc JOIN $ta ON $tc.class_id=$ta.root_id AND $tc.network_id=$ta.network_id              
                WHERE $tc.network_id = ? 
                  AND $ta.anno_status = " . NC_ACTIVE . "
                  AND $ta.root_id LIKE '" . NC_PREFIX_CLASS . "%' 
                  AND $tac <=" . NC_DEFS . " $onto GROUP BY $ta.root_id, $tac";

        $sql = "SELECT class_id, parent_id, connector, directional, class_status AS status, $sqlgroup            
            FROM ($innersql) AS T GROUP BY class_id ORDER BY parent_id, name";

        $stmt = $this->qPE($sql, [$this->_netid]);

        $result = array();
        if ($idkeys == true) {
            while ($row = $stmt->fetch()) {
                $result[$row['class_id']] = $row;
            }
        } else {
            while ($row = $stmt->fetch()) {
                $result[$row['name']] = $row;
            }
        }
        return $result;
    }

    /**
     * Fetches basic class information: id (Cxxxxxx), class parent, node/link (connector),
     * directionality, anno_id for the name, datetime of creation, and owner id. 
     * 
     * @param string $classname
     * 
     * @param boolean $throw
     * 
     * set true to throw an exception if the classname does not match any existing classes.
     * 
     * @return array
     *      
     * @throws Exception
     */
    protected function getClassInfo($classname, $throw = true) {
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;

        $sql = "SELECT class_id, $tc.parent_id AS parent_id, connector, 
            directional, class_status, anno_text AS class_name, anno_id,  
            datetime, owner_id             
              FROM $tc JOIN $tat 
                ON $tc.class_id=$tat.root_id AND $tc.network_id=$tat.network_id
              WHERE $tc.network_id = ? AND root_id LIKE '" . NC_PREFIX_CLASS . "%' 
                  AND $tat.anno_text = ?
                  AND $tat.anno_type= " . NC_NAME . "
                  AND $tat.anno_status=" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classname]);
        $classinfo = $stmt->fetch();
        if (!$classinfo) {
            if ($throw) {
                throw new Exception("Class '$classname' does not exist");
            } else {
                return null;
            }
        }
        return $classinfo;
    }

    /**
     * Fetches current information on a given class
     * 
     * @param string $classid
     * 
     * id string
     * 
     * @return array
     * 
     * data describing a given class_name
     *
     * @throws Exception
     * 
     * when the classid does not match db records
     * 
     */
    private function getClassRecord($classid) {

        $sql = "SELECT class_id, parent_id, connector, directional, class_status
            FROM " . NC_TABLE_CLASSES . "
                WHERE network_id = ? AND class_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        $classinfo = $stmt->fetch();
        if (!$classinfo) {
            throw new Exception("Class $classid does not exist");
        }
        return $classinfo;
    }

    /**
     * Helper function performs all db operations associated with creating new ontology class
     * 
     * @param array $params
     * 
     * array prepared as in createNewClass()
     * 
     * @return string
     * 
     * string holding the new class id
     * 
     * @throws Exception
     */
    protected function createNewClassWork($params) {

        if (strlen($params['name']) < 2) {
            throw new Exception("Class name too short");
        }

        // check if class name is alredy taken
        $classid = $this->getClassInfo($params['name'], false);
        if ($classid != null) {
            throw new Exception("Class name " . $params['name'] . " already exists");
        }
        // check properties of the parent
        if ($params['parent'] != '') {
            $parentinfo = $this->getClassInfo($params['parent']);
            if ($params['connector'] != $parentinfo['connector']) {
                throw new Exception("Incompatible connector settings");
            }
            if ($params['directional'] < $parentinfo['directional']) {
                throw new Exception("Incompatible directional settings");
            }
            $parentid = $parentinfo['class_id'];
        } else {
            $parentid = '';
        }

        // append default def if now present
        if ($params['defs'] == '') {
            if ($params['connector'] == 0) {
                $params['defs'] = $this->_def_node[0] . ' id="' . $params['name'] . '" '
                        . $this->_def_node[1];
            } else {
                $params['defs'] = $this->_def_link;
            }
        }

        // if reached here, the class is ok to be inserted
        $newid = $this->makeRandomID(NC_TABLE_CLASSES, "class_id", NC_PREFIX_CLASS, NC_ID_LEN);

        // create/insert the new class into the classes table
        $sql = "INSERT INTO " . NC_TABLE_CLASSES . "
                   (network_id, class_id, parent_id, connector, directional) 
                   VALUES (?, ?, ?, ?, ?)";
        $this->qPE($sql, [$this->_netid, $newid, $parentid, $params['connector'], $params['directional']]);

        // create an annotation set (registers the name of the class)                  
        $this->batchInsertAnnoSets($this->_netid, [$params], [$newid]);

        return $newid;
    }

    /**
     * Define a new class. The function inserts a new entry into the 
     * classes table and accompanying annotations in anno_text
     * 
     * @return type
     * @throws Exception
     */
    public function createNewClass() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, array_merge(["parent",
                    "connector", "directional"], array_keys($this->_annotypes)));

        // perform the db actions
        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        $newid = $this->createNewClassWork($params);
        $this->dbunlock();

        // log the activity and send email
        $this->logActivity($this->_uid, $this->_netid, "created new class", $params['name'], $newid);
        $this->sendNewClassEmail($params['name']);
                
        return $newid;
    }

    /**
     * Helper function that can be used to check if a queryid can be a parent of childid.
     * An id cannot be a parent if itself has the childid as a parent. Uses recursion to 
     * traverse a complete hierarchy up to the root.
     * 
     * @param $queryid
     * 
     * id of an ontology node, function will look at this id and all its parents
     * 
     * @param $avoidid
     * 
     * id that should not be allowed among the parents
     * 
     * @return boolean
     * 
     * true if the hierarchy is ok, false otherwise
     */
    private function canBeParent($parentid, $childid) {

        // edge case for recursion
        if ($parentid == "") {
            return true;
        }

        // get information about this id
        $queryrecord = $this->getClassRecord($parentid);

        // check for bad hierarchy, or keep checking
        if ($queryrecord['parent_id'] == $childid) {
            return false;
        } else {
            return $this->canBeParent($queryrecord['parent_id'], $childid);
        }
    }

    /**
     * 
     * Helper function applies db transformations on an ontology class
     * 
     * @param type $params
     * @throws Exception
     */
    protected function updateClassWork($params) {

        if (strlen($params['name']) < 2) {
            throw new Exception("Class name too short");
        }

        // fetch the current information about this class        
        $oldinfo = $this->getClassInfo($params['target']);
        $classid = $oldinfo['class_id'];
        $parentid = '';
        if ($params['parent'] != '') {
            $parentinfo = $this->getClassInfo($params['parent']);
            $parentid = $parentinfo['class_id'];
        }

        // check that new data is different from existing data
        $Q1 = $parentid == $oldinfo['parent_id'];
        $Q2 = $params['name'] == $params['target'];
        $Q3 = $params['directional'] == $oldinfo['directional'];
        $Q4 = $params['connector'] == $oldinfo['connector'];
        $Q5 = $params['status'] == $oldinfo['class_status'];
        if ($Q1 && $Q2 && $Q3 && $Q4 && $Q5) {
            // the class information is mostly the same, but maybe the defs still need change            
        }
        if (!$Q4) {
            throw new Exception("Cannot toggle connector status");
        }
        if (!$Q5) {
            throw new Exception("Cannot toggle status (use another function for that)");
        }

        // if the parent is non-trivial, further compatibility checks
        if ($params['parent'] != "") {
            // connector status must match
            if ($parentinfo['connector'] != $params['connector']) {
                throw new Exception("Incompatible class/parent connector status");
            }

            // for links that are non-directional, make sure the parent is also 
            if (!$params['directional'] && $params['connector']) {
                if ($parentinfo['directional'] > $params['directional']) {
                    throw new Exception("Incompatible directional status (parent is a directional link)");
                }
            }

            // make sure there is hierarchy, i.e. none of the parents themselves refer to this class            
            if (!$this->canBeParent($parentid, $classid)) {
                throw new Exception("Incorrect hierarchy (ancenstor lists class as a parent)");
            }
        }

        // if here, the class needs updating.
        $result = 0;
        if (!$Q2) {
            // here, the class name needs updating, but is it available?
            $sql = "SELECT anno_id FROM " . NC_TABLE_ANNOTEXT . " WHERE
                        network_id = ? AND anno_type=" . NC_NAME . " 
                    AND root_id LIKE 'C%' AND anno_text = ? AND anno_status=" . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $params['name']]);
            if ($stmt->fetch()) {
                throw new Exception("Class name already exists");
            }

            // update the class name and log the activity
            $pp = $this->subsetArray($oldinfo, ['owner_id', 'datetime', 'anno_id']);
            $pp = array_merge($pp, ['network_id' => $this->_netid,
                'parent_id' => $parentid, 'root_id' => $classid,
                'anno_text' => $params['name'], 'anno_type' => NC_NAME]);
            $this->batchUpdateAnno([$pp]);
            $result = 1;
        }

        // perhaps update the title, abstract, content, or defs
        $updaterest = false;
        foreach (['title', 'abstract', 'content', 'defs'] as $annotype) {
            if ($params[$annotype] != '') {
                $updaterest = true;
            }
        }
        if ($updaterest) {
            $oldfullinfo = $this->getFullSummaryFromRootId($this->_netid, $classid);
            $batchupdate = [];
            foreach (['title', 'abstract', 'content', 'defs'] as $annotype) {
                if ($params[$annotype] != '' &&
                        $params[$annotype] != $oldfullinfo[$annotype]['anno_text']) {
                    // update this annotation
                    $pp = $oldfullinfo[$annotype];
                    $pp['anno_text'] = $params[$annotype];
                    $batchupdate[] = $pp;
                }
            }
            $this->batchUpdateAnno($batchupdate);
            if (count($batchupdate) > 0) {
                $result += 2;
            }
        }

        if (!$Q1 || !$Q3) {
            // update the class structure and log the activity
            $this->updateClassStructure($oldinfo['class_id'], $parentid, $params['directional'], $params['status']);
            $result +=4;
        }

        return $result;
    }

    /**
     * Helper function applies an update transformation on a 
     * 
     * @param type $parentid
     * @param type $directional
     * @param type $classstatus
     * @param type $classid
     */
    protected function updateClassStructure($classid, $parentid, $directional, $status) {
        $sql = "UPDATE " . NC_TABLE_CLASSES . "  SET 
            parent_id= ? , directional= ?, class_status= ? WHERE class_id = ?";
        $this->qPE($sql, [$parentid, $directional, $status, $classid]);
    }

    /**
     * Changes properties or annotations associated with a given class
     * 
     * @return string
     * 
     * @throws Exception
     */
    public function updateClass() {

        // check that required inputs are defined
        $params = $this->subsetArray($this->_params, array_merge(["target", "parent",
                    "connector", "directional", "defs", "status"], array_keys($this->_annotypes)));

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        if ($params["status"] != 1) {
            throw new Exception("Use a different function to change class status");
        }

        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        $action = $this->updateClassWork($params);
        $this->dbunlock();

        // perform logging based on what actions were performed
        if ($action == 1) {
            $this->logActivity($this->_uid, $this->_netid, "updated class name", $params['target'], $params['name']);
            $this->sendUpdateClassEmail("new class name");
        }
        if ($action == 2 || $action == 3) {
            $this->logActivity($this->_uid, $this->_netid, "updated class style", $params['target'], $params['name']);
            $this->sendUpdateClassEmail("new class style");
        }
        if ($action >= 4) {
            $value = $params['parent'] . "," . $params['directional'] . "," . $params['status'];
            $this->logActivity($this->_uid, $this->_netid, "updated class properties for class", $params['target'], $value);
            $this->sendUpdateClassEmail("new class properties");
        }

        return 1;
    }

    /**
     * Helper function performs DB actions associated with deprecating/deleting an ontology class
     * 
     * @param string $classname
     * @return boolean
     * 
     * true if the action results in full deletion of the class
     * false if the action results in deprecating the class
     * 
     * @throws Exception
     */
    protected function removeClassWork($classname) {

        // fetch information about the class
        $oldinfo = $this->getClassInfo($classname);
        $classid = $oldinfo['class_id'];

        // class exists in db. Check if it has been used already in a nontrivial way
        $tc = "" . NC_TABLE_CLASSES;
        $tat = "" . NC_TABLE_ANNOTEXT;
        $tan = "" . NC_TABLE_ANNONUM;

        // does the class have active descendants?
        $sql = "SELECT class_id FROM $tc WHERE network_id = ? 
                    AND parent_id = ? AND class_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        if ($stmt->fetch()) {
            throw new Exception("Cannot remove: class has active descendants");
        }

        // are there nodes or links that use this class?
        $sql = "SELECT COUNT(*) AS count FROM ";
        if ($oldinfo['connector']) {
            $sql .= NC_TABLE_LINKS;
        } else {
            $sql .= NC_TABLE_NODES;
        }
        $sql .= " WHERE network_id = ? AND class_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid, $classid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching class usage");
        }

        if ($result['count'] == 0) {
            // class is not used - remove it permanently from all tables            
            $sql = "DELETE FROM $tc WHERE network_id = ? AND class_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            $sql = "DELETE FROM $tat WHERE network_id = ? AND root_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            $sql = "DELETE FROM $tan WHERE network_id = ? AND root_id = ?";
            $this->qPE($sql, [$this->_netid, $classid]);
            return true; //"Class has been removed entirely";            
        } else {
            // class is used - set as inactive
            $sql = "UPDATE $tc SET class_status = " . NC_DEPRECATED . " WHERE 
                     network_id = ? AND class_id = ? ";
            $this->qPE($sql, [$this->_netid, $classid]);
            return false; //"Class id has already been used";            
        }
    }

    /**
     * Either deletes or inactivates a given class
     */
    public function removeClass() {

        // check that required inputs are defined
        $classname = $this->subsetArray($this->_params, ["name"])['name'];

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        // perform the removal action
        $this->dblock([NC_TABLE_CLASSES, NC_TABLE_ANNOTEXT, NC_TABLE_ANNONUM,
            NC_TABLE_ACTIVITY, NC_TABLE_LINKS, NC_TABLE_NODES]);
        $action = $this->removeClassWork($classname);
        $this->dbunlock();

        // log the action
        if ($action) {
            $this->logActivity($this->_uid, $this->_netid, "deleted class", $classname, $classname);
            $this->sendUpdateClassEmail("deleted class");
        } else {
            $this->logActivity($this->_uid, $this->_netid, "deprecated class", $classname, $classname);
            $this->sendUpdateClassEmail("depreacted");
        }

        return $action;
    }

    /**
     * Helper function performs DB actions associating with activating a previously deprecated class
     * 
     * @param string $classname
     */
    protected function activateClassWork($classname) {

        // check class actually needs activating
        $classinfo = $this->getClassInfo($classname);
        if ($classinfo['class_status'] != NC_DEPRECATED) {
            throw new Exception("Class is not deprecated");
        }

        // parent class must be active
        if ($classinfo['parent_id'] != '') {
            $parentinfo = $this->getClassRecord($classinfo['parent_id']);
            if ($parentinfo['class_status'] != NC_ACTIVE) {
                throw new Exception("Class cannot be activated because parent is deprecated");
            }
        }

        // finally just set new status 
        $sql = "UPDATE " . NC_TABLE_CLASSES . " SET class_status = " . NC_ACTIVE . "
            WHERE network_id = ? AND class_id = ? ";
        $this->qPE($sql, [$this->_netid, $classinfo['class_id']]);
    }

    /**
     * Used to turn a deprecated class status into an active class status
     * 
     * This is a rather simple function as it does not affect any of the nodes/links.
     * 
     * @return boolean
     * 
     * Usually returns true upon successful completion. Otherwise throws exceptions.
     * 
     * @throws Exception
     * 
     */
    public function activateClass() {

        // check that required inputs are defined
        $classname = $this->subsetArray($this->_params, ["name"])['name'];

        // make sure the asking user is allowed to curate
        if ($this->_uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        $this->dblock([NC_TABLE_ANNOTEXT, NC_TABLE_CLASSES]);
        $this->activateClassWork($classname);
        $this->dbunlock();

        // log the activity
        $this->logActivity($this->_uid, $this->_netid, "re-activated class", $classname, $classname);
        $this->sendUpdateClassEmail("activate class");
        
        return true;
    }

    /**
     * Send an email with a summary of the ontology update to curators
     */
    private function sendUpdateClassEmail($updatetype) {        
        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK'=>$this->_network, 
            'CLASS'=>$this->_params['name'], 
            'USER'=>$this->_uid,
            'UPDATE'=>$updatetype];
        $ncemail->sendEmailToCurators("email-update-class", $emaildata, $this->_netid);
    }

    /**
     * Send an email about a new ontology class to curators
     */
    private function sendNewClassEmail() {
        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK'=>$this->_network, 
            'CLASS'=>$this->_params['name'], 
            'USER'=>$this->_uid];
        $ncemail->sendEmailToCurators("email-new-class", $emaildata, $this->_netid);
    }

}

?>
