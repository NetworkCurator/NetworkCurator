<?php

include_once "NCDB.php";
include_once "NCTimer.php";

/*
 * Class handling logging activity into the _activity and _log tables.
 * It also forms the basis for all the controllers.
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */

class NCLogger extends NCDB {

    // class inherits _db from NCCacher
    // here define other parameters
    protected $_params;
    protected $_uname; // user name (string)
    protected $_uid; // user_id (or 0 for guest)
    protected $_upw; // user_confirmation code (or guest)
    private $_log = true;
    private $_counter = 0;

    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */
    public function __construct($db, $params) {
        parent::__construct($db);

        if (isset($params['user_name'])) {
            $this->_uname = $params['user_name'];
            $udata = $this->getUserData($params['user_name']);
            $this->_uid = (int) $udata['user_id'];
            $this->_upw = $udata['user_extpwd'];
        } else {
            throw new Exception("Missing required parameter user_name");
        }
        unset($params['user_name']);

        $this->_params = $params;
    }

    /**
     * Resets the parameters for this class.
     * This is used when the API class is used internally.
     * 
     * @param type $params
     */
    public function resetParams($params) {
        $this->_params = $params;
    }

    /**
     * Set logging for this class. By default logging is on.
     * 
     * @param type $tolog
     */
    public function setLogging($tolog) {
        $this->_log = $tolog;
    }

    /**
     * Create an id string that is not already present in a dbtable table
     * 
     * @param type $dbtable
     * 
     * name of table in database to query
     * 
     * @param type $idcolumn
     * 
     * column in dbtable holding ids.
     * 
     * @param type $idprefix
     * 
     * prefix for random id - e.g. to make Nxxxxxx for nodes or Lxxxxxx for links
     * 
     * @param type $stringlength
     * 
     * integer, number of hex digits in the random id (excluding prefix)
     * 
     */
    protected function makeRandomID($dbtable, $idcolumn, $idprefix, $stringlength) {

        echo "makeRandomID: should be deprecated\n";

        // this does not use prepared statements at the moment                
        $newid = "";
        $keeplooking = true;
        while ($keeplooking) {
            $newid = $idprefix . makeRandomString($stringlength);
            $sql = "SELECT $idcolumn FROM $dbtable WHERE $idcolumn='$newid'";
            $keeplooking = $this->_db->query($sql)->fetch();
        }
        return $newid;
    }

    /**
     * Add entry into log table
     * 
     * @param int $userid
     * 
     * keep this as userid (int)
     * 
     * @param string $userip
     * @param string $action
     * @param string $value
     * @throws Exception
     * 
     */
    protected function logAction($userid, $userip, $controller, $action, $value) {
        if ($this->_log) {
            // prepare a statement for log-logging
            $sqllog = "INSERT INTO " . NC_TABLE_LOG . "
            (datetime, user_id, user_ip, controller, action, value) VALUES 
            (UTC_TIMESTAMP(), :user_name, :user_ip, :controller, :action, :value)";
            $stmt = $this->_db->prepare($sqllog);
            // execture the query with current parameters
            $pp = array('user_id' => $userid, 'user_ip' => $userip,
                'controller' => $controller, 'action' => $action,
                'value' => $value);
            $stmt->execute($pp);
        }
        return 1;
    }

    /**
     * Add entry into activity table
     * 
     * @param string $username
     * @param type $netid
     * @param type $action
     * @param type $targetid
     * @param type $value
     * @throws Exception
     */
    protected function logActivity($username, $netid, $action, $targetname, $value) {
        if ($this->_log) {
            // prepare a statement for activity-logging
            $sqlact = "INSERT INTO " . NC_TABLE_ACTIVITY . "
                   (datetime, user_name, network_id, action, target_name, value) 
                   VALUES 
                   (UTC_TIMESTAMP(), :user_name, :network_id, :action, 
                       :target_name, :value)";
            $stmt = $this->_db->prepare($sqlact);
            // execute the query with current parameters
            $pp = array('user_name' => $username, 'network_id' => $netid,
                'action' => $action, 'target_name' => $targetname,
                'value' => $value);
            $stmt->execute($pp);
        }
        return 1;
    }

    /**
     * Execute a db query that insert an annotation into the table. 
     * 
     * The action insert one row with status set to active.
     * 
     * @param array $params
     * 
     * the array should have entries: 
     * network_id, owner_id, user_id, root_id, root_type, parent_id, anno_text, anno_type
     * 
     * @return 
     * 
     * id code for the new annotation
     * 
     */
    protected function insertAnnoText($params) {

        // get a new annotation id
        $tat = "" . NC_TABLE_ANNOTEXT;
        //$newid = $this->makeRandomID($tat, 'anno_id', 'AT', NC_ID_LEN);
        //$params['anno_id'] = $newid;
        // insert the annotation into the table
        $sql = "INSERT INTO $tat 
                   (datetime, network_id, owner_id, user_id, root_id, root_type, 
                   parent_id, anno_name, anno_text, anno_type, anno_status) VALUES 
                   (UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :root_type, 
                   :parent_id, :anno_name, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        $this->qPE($sql, $params);

        //return $newid;
    }

    /**
     * Updates the annotext table with some new data.
     * Updating means changing an existing row with anno_id to status=OLD,
     * and inserting a new row with status = active. 
     * 
     * @param array $params
     * 
     * The function assumes the array contains certain elements (see start of code)
     * 
     * datetime - refers to the datetime or creation of original annotation entry
     * 
     * 
     * @return
     * 
     * the new annotation id
     *      
     */
    protected function updateAnnoText($pp) {

        $params = $this->subsetArray($pp, ["network_id", "datetime",
            "owner_id", "root_id", "root_type", "parent_id",
            "anno_id", "anno_name", "anno_text", "anno_type"]);
        $params['user_id'] = $this->_uid;

        if ($params['anno_text'] == '') {
            throw new Exception("Cannot insert empty annotation");
        }

        $tat = "" . NC_TABLE_ANNOTEXT;

        // set anno_status to disabled. This anno_id becomes the historical record
        $sql = "UPDATE $tat SET anno_status=" . NC_OLD . "                           
                WHERE network_id = ? AND anno_id = ? AND anno_status=" . NC_ACTIVE;
        $this->qPE($sql, [$params['network_id'], $params['anno_id']]);

        // insert an extra copy. This becomes the active annotation 
        $sql = "INSERT INTO $tat 
                   (datetime, modified, network_id, owner_id, user_id, 
                   root_id, root_type, parent_id, 
                   anno_name, anno_text, anno_type, anno_status) VALUES                          
                   (:datetime, UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, 
                   :root_id, :root_type, :parent_id,
                   :anno_name, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        unset($params['anno_id']);
        $this->qPE($sql, $params);
        return $this->lID();
    }

    /**
     * looks up the permission code for a user on a network (given a name)
     *
     * This function takes network and uid separately from the class _network
     * and _uid. This allows, for example, the admin user to get the 
     * permission for the guest user. 
     * 
     * @param type $network
     * @param type $uid
     * @throws Exception
     */
    protected function getUserPermissions($network, $uid) {

        throw new Exception("getUserPermissions is deprecated");

        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $sql = "SELECT permissions            
            FROM $tp JOIN $tn ON $tp.network_id = $tn.network_id                
            WHERE $tp.user_id = ? AND $tn.network_name = ?";
        $stmt = $this->_db->prepare($sql);
        $stmt = $stmt->execute(array($uid, $network));
        $result = $stmt->fetch();
        if (!$result) {
            return NC_PERM_NONE;
        }
        return (int) $result['permissions'];
    }

    /**
     * looks up the permission code for a user on a network (given an id)
     *
     * This function takes network and uid separately from the class _network
     * and _uid. This allows, for example, the admin user to get the 
     * permission for the guest user. 
     * 
     * @param type $network
     * @param type $uid
     * @throws Exception
     */
    protected function getUserPermissionsNetID($netid, $uid) {
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS .
                " WHERE user_id = ? AND network_id = ?";
        $stmt = $this->qPE($sql, [$uid, $netid]);
        $result = $stmt->fetch();
        if (!$result) {
            return NC_PERM_NONE;
        }
        return (int) $result['permissions'];
    }

    /**
     * get a network id associated with a network name
     * 
     * This requires a lookup for the current name in the annotations table.
     * 
     * @param string $netname
     * 
     * A network name like "my-network"
     * 
     * @param logical $throw
     * 
     * set true to automatically throw an exception if the network does not exist
     * 
     * @return string
     * 
     * A code used in the db, e.g. "Wxxxxxx".
     * If a network does nto exists, return the empty string.
     *  
     */
    protected function getNetworkId($netname, $throw = false) {
        $sql = "SELECT network_id FROM " . NC_TABLE_ANNOTEXT . "
            WHERE BINARY anno_text = ? AND anno_type = " . NC_NAME . " 
                AND anno_status = 1";
        $stmt = $this->qPE($sql, [$netname]);
        $result = $stmt->fetch();
        if (!$result) {
            if ($throw) {
                throw new Exception("Network does not exist");
            }
            return -1;
        } else {
            return (int) $result['network_id'];
        }
    }

    /**
     * Converts between a user_name (string) and user_id (integer)
     * 
     * @param type $username
     * @return type
     * @throws Exception
     */
    protected function getUserData($username) {
        $sql = "SELECT user_id, user_extpwd FROM " . NC_TABLE_USERS . "
            WHERE BINARY user_name = ? AND user_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$username]);
        $result = $stmt->fetch();
        if (!$result) {
            return ['user_id' => NC_USER_GUEST, 'user_extpwd' => "guest"];
        } else {
            return $result;
        }
    }

    /**
     * Get the root id associated with a given name-level annotation.
     * E.g. if we know a class name is "GOOD_NODE", this function will
     * return the "Cxxxxxx" code associated with this class name.
     * 
     * 
     * @param int $netname
     * @param int $nameanno
     * @param int $rootype
     * 
     * @return string 
     * 
     * The root id, or empty string when the name annotation does not match
     */
    protected function getNameAnnoRootId($netid, $nameanno, $roottype, $throw = true) {

        $sql = "SELECT root_id, root_type, anno_status FROM " . NC_TABLE_ANNOTEXT . "
           WHERE network_id = ? AND anno_text = ? AND root_type = ? 
           AND anno_type = " . NC_NAME . " AND anno_status != " . NC_OLD;
        $stmt = $this->qPE($sql, [$netid, $nameanno, $roottype]);
        $result = $stmt->fetch();
        if ($throw) {
            if (!$result) {
                throw new Exception("Name does not match any annotations");
            }
        }
        return $result;
    }

    /**
     * Fetches the anno_id associated with an annotation
     * (e.g. if want to update the title of a network,
     * set netid, rootid=netid, level=NC_NAME
     * 
     * @param type $netid
     * @param type $rootid
     * @param type $level
     * @return string
     * 
     * the anno_id
     * 
     * @throws Exception
     * 
     * (should never happen as this function is used internaly)
     * 
     */
    protected function getAnnoInfo($netid, $rootid, $roottype, $annotype) {
        if ($annotype != NC_ABSTRACT && $annotype == NC_TITLE && $annotype == NC_CONTENT) {
            throw new Exception("Function only suitable for title, abstract, content");
        }
        $sql = "SELECT anno_id, datetime, owner_id, anno_name FROM " . NC_TABLE_ANNOTEXT . " 
            WHERE BINARY network_id = ? AND root_id = ? AND root_type= ? 
               AND anno_type = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$netid, $rootid, $roottype, $annotype]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching anno id");
        }
        return $result;
    }

    /**
     * Helper function to create a set of annotations for 
     * name, title, abstract, content
     * 
     * Only the annotation name and title are set. The others are created, but
     * set empty. 
     * 
     * @param string $netid
     * @param string $uid
     * @param string $rootid
     * @param string $annoname
     * @param string $annotitle
     */
    protected function insertNewAnnoSetOld($netid, $uid, $rootid, $annoname, $annotitle, $annoabstract = 'empty', $annocontent = 'empty') {
// create starting annotations for the title, abstract and content        

        throw new Exception("insertNewAnnoSetOld is deprecated");

        $params = array('network_id' => $netid, 'user_id' => $uid, 'owner_id' => $uid,
            'root_id' => $rootid, 'parent_id' => $rootid, 'anno_type' => NC_NAME,
            'anno_text' => $annoname);
// insert object name
        $this->insertAnnoText($params);
// insert title        
        $params['anno_text'] = $annotitle;
        $params['anno_type'] = NC_TITLE;
        $this->insertAnnoText($params);
// insert abstract                
        $params['anno_text'] = $annoabstract;
        $params['anno_type'] = NC_ABSTRACT;
        $this->insertAnnoText($params);
        // insert content
        $params['anno_text'] = $annocontent;
        $params['anno_type'] = NC_CONTENT;
        $this->insertAnnoText($params);
    }

    /**
     * New way of inserting a set of annotation that does not use anno_id lookup
     * and uses only on insert statement.
     * 
     * @param type $netid
     * @param type $uid
     * @param type $rootid
     * @param type $annoname
     * @param type $annotitle
     * @param type $annoabstract
     * @param type $annocontent
     * 
     */
    protected function insertNewAnnoSet($netid, $uid, $rootid, $roottype, $annoname, $annotitle = 'empty', $annoabstract = 'empty', $annocontent = 'empty') {

        // create an array of parameter values (one set for name, abstrat, title, content)
        $params = [];
        foreach (["A", "B", "C", "D"] as $abc) {
            $params['network_id' . $abc] = $netid;
            $params['owner_id' . $abc] = $uid;
            $params['user_id' . $abc] = $uid;
            $params['root_id' . $abc] = $rootid;
            $params['parent_id' . $abc] = $rootid;
            $params['root_type' . $abc] = $roottype;
        }
        $basename = "T_" . $rootid . "_" . $roottype . "_";
        $params['anno_nameA'] = $basename . NC_NAME;
        $params['anno_nameB'] = $basename . NC_TITLE;
        $params['anno_nameC'] = $basename . NC_ABSTRACT;
        $params['anno_nameD'] = $basename . NC_CONTENT;
        $params['anno_textA'] = $annoname;
        $params['anno_textB'] = $annotitle;
        $params['anno_textC'] = $annoabstract;
        $params['anno_textD'] = $annocontent;

        $sql = "INSERT INTO " . NC_TABLE_ANNOTEXT . "
             (datetime, network_id, owner_id, user_id, root_id, root_type, parent_id, 
             anno_name, anno_text, anno_type, anno_status) VALUES 
             (UTC_TIMESTAMP(), :network_idA, :owner_idA, :user_idA, :root_idA, :root_typeA, :parent_idA,
             :anno_nameA, :anno_textA, " . NC_NAME . ", " . NC_ACTIVE . "),                       
             (UTC_TIMESTAMP(), :network_idB, :owner_idB, :user_idB, :root_idB, :root_typeB, :parent_idB,
             :anno_nameB, :anno_textB, " . NC_TITLE . ", " . NC_ACTIVE . "),
             (UTC_TIMESTAMP(), :network_idC, :owner_idC, :user_idC, :root_idC, :root_typeC, :parent_idC,
             :anno_nameC, :anno_textC, " . NC_ABSTRACT . ", " . NC_ACTIVE . "),
             (UTC_TIMESTAMP(), :network_idD, :owner_idD, :user_idD, :root_idD, :root_typeD, :parent_idD,
             :anno_nameD, :anno_textD, " . NC_CONTENT . ", " . NC_ACTIVE . ")";

        $this->qPE($sql, $params);
    }

    /**
     * Get a small array using only a few elements from a larger (assoc) array
     * 
     * @param array $array
     * @param array $keys
     * @return array
     * 
     */
    protected function subsetArray($array, $keys) {

        // perform the subset
        $result = array_intersect_key($array, array_flip($keys));

        // check if all the keys were found
        if (count($result) !== count($keys)) {
            // some of the keys are missing, make a string with a summary
            $missing = "";
            for ($i = 0; $i < count($keys); $i++) {
                if (!array_key_exists($keys[$i], $array)) {
                    $missing .= " " . $keys[$i];
                }
            }
            throw new Exception("Missing keys: $missing");
        }

        return $result;
    }

    protected function tick() {
        echo ($this->_counter) . " ... ";
        $this->_counter = $this->_counter + 1;        
    }

}

?>
