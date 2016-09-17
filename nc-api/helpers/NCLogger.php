<?php

/*
 * Class handling logging activity into the _activity and _log tables.
 * It also forms the basis for all the controllers.
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */

class NCLogger {

    // general connection
    protected $_db;
    protected $_params;
    protected $_uid; // user_id (or guest)
    protected $_upw; // user_confirmation code (or guest(

    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */

    public function __construct($db, $_params) {
        $this->_db = $db;
        $this->_params = $_params;
        $this->_uid = $_paramas['user_id'];
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
        // this does not use prepared statements at the moment
        //echo "making random ID\n";
        $newid = "";
        $keeplooking = true;
        while ($keeplooking) {
            $newid = $idprefix . makeRandomString($stringlength);
            $sql = "SELECT $idcolumn FROM $dbtable WHERE $idcolumn='$newid'";
            $keeplooking = $this->_db->query($sql)->fetchAll();
        }
        return $newid;
    }

    /**
     * Add entry into log table
     * 
     * @param string $userid
     * @param string $userip
     * @param string $action
     * @param string $value
     * @throws Exception
     * 
     */
    public function logAction($userid, $userip, $controller, $action, $value) {
        // prepare a statement for log-logging
        $sqllog = "INSERT INTO " . NC_TABLE_LOG . "
            (datetime, user_id, user_ip, controller, action, value) VALUES 
            (UTC_TIMESTAMP(), :user_id, :user_ip, :controller, :action, :value)";
        $stmt = $this->_db->prepare($sqllog);
        // execture the query with current parameters
        $pp = array('user_id' => $userid, 'user_ip' => $userip,
            'controller' => $controller, 'action' => $action,
            'value' => $value);
        $stmt->execute($pp);
        return 1;
    }

    /**
     * Add entry into activity table
     * 
     * @param type $uid
     * @param type $netid
     * @param type $action
     * @param type $targetid
     * @param type $value
     * @throws Exception
     */
    public function logActivity($userid, $netid, $action, $targetname, $value) {
        // prepare a statement for activity-logging
        $sqlact = "INSERT INTO " . NC_TABLE_ACTIVITY . "
                   (datetime, user_id, network_id, action, target_name, value) 
                   VALUES 
                   (UTC_TIMESTAMP(), :user_id, :network_id, :action, 
                       :target_name, :value)";
        $stmt = $this->_db->prepare($sqlact);
        // execute the query with current parameters
        $pp = array('user_id' => $userid, 'network_id' => $netid,
            'action' => $action, 'target_name' => $targetname,
            'value' => $value);
        $stmt->execute($pp);
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
     * network_id, owner_id, user_id, root_id, parent_id, anno_text, anno_level
     * 
     * @return 
     * 
     * id code for the new annotation
     * 
     */
    public function insertAnnoText($params) {

        // get a new annotation id
        $tat = "" . NC_TABLE_ANNOTEXT;
        $newid = $this->makeRandomID($tat, 'anno_id', 'AT', NC_ID_LEN);

        $params['anno_id'] = $newid;

        // insert the annotation into the table
        // (this is somewhat inelegant, uses twice the named placeholders with A/B.
        $sql = "INSERT INTO $tat 
                   (datetime, network_id, owner_id, user_id, root_id, parent_id, 
                   anno_id, anno_text, anno_level, anno_status) VALUES 
                   (UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                   :anno_id, :anno_text, :anno_level, " . NC_ACTIVE . ")";
        $stmt = prepexec($this->_db, $sql, $params);

        return $newid;
    }
        
    /**
     * Updates the annotext table with some new data.
     * Updating means changing an existing row with anno_id to status=OLD,
     * and inserting a new row with status = active. 
     * 
     * @param array $params
     * 
     * The function assumes the array contains seven elements.
     * network_id, owner_id, user_id, root_id, parent_id, anno_text, anno_level
     * 
     * @return
     * 
     * integer 1 upon success
     *      
     */
    public function updateAnnoText($params) {

        $tat = "" . NC_TABLE_ANNOTEXT;

        // fetch the old date
        $sql = "SELECT datetime FROM $tat WHERE network_id = ? AND anno_id = ? 
            AND anno_status = ".NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$params['network_id'], $params['anno_id']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Could not identify annotation");
        }
        $olddate = $result['datetime'];
        
        // prepare a statement setting all anno_status to disabled for a given anno_id
        $sql = "UPDATE $tat SET anno_status=" . NC_OLD . "                           
                WHERE network_id = ? AND anno_id = ? AND anno_status=" . NC_ACTIVE;
        $stmt = prepexec($this->_db, $sql, [$params['network_id'], $params['anno_id']]);

        // insert an extra copy for historical records
        $sql = "INSERT INTO $tat 
                   (datetime, modified, network_id, owner_id, user_id, root_id, parent_id, 
                   anno_id, anno_text, anno_level, anno_status) VALUES                          
                   ('$olddate', UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                   :anno_id, :anno_text, :anno_level, " . NC_ACTIVE . ")";
        $stmt = prepexec($this->_db, $sql, $params);

        return 1;
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
        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $sql = "SELECT permissions            
            FROM $tp JOIN $tn ON $tp.network_id = $tn.network_id                
            WHERE BINARY $tp.user_id = ? AND $tn.network_name = ?";
        $stmt = $this->_db->prepare($sql);
        $stmt = $stmt->execute(array($uid, $network));
        $result = $stmt . fetch();
        if (!$result) {
            return NC_PERM_NONE;
        }
        return (int) $result[permissions];
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
                " WHERE BINARY user_id = ? AND network_id = ?";
        $stmt = prepexec($this->_db, $sql, [$uid, $netid]);
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
     * A code used in the db, e.g. "W0123456789".
     * If a network does nto exists, return the empty string.
     *  
     */
    protected function getNetworkId($netname, $throw=false) {
        //echo "getNetworkID\n";
        $sql = "SELECT network_id FROM " . NC_TABLE_ANNOTEXT . "
            WHERE BINARY anno_text = ? AND anno_level = " . NC_NAME . " 
                AND anno_status = 1";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute([$netname]);
        $result = $stmt->fetch();
        if (!$result) {
            if ($throw) {
                throw new Exception("Network does not exist");
            } 
            return "";
        } else {
            return $result['network_id'];
        }
    }

    /**
     * Get the root id associated with a given name-level annotation.
     * E.g. if we know a class name is "GOOD_NODE", this function will
     * return the "Cxxxxxx" code associated with this class name.
     * 
     * @param type $netname
     * @param type $nameanno
     * 
     * @return string 
     * 
     * The root id, or empty string when the name annotation does not match
     */
    protected function getNameAnnoRootId($netid, $nameanno, $throw = true) {
        $sql = "SELECT root_id, anno_status FROM " . NC_TABLE_ANNOTEXT . "
           WHERE BINARY network_id = ? AND anno_text = ? AND 
           anno_level = " . NC_NAME . " AND anno_status != ".NC_OLD;
        $stmt = prepexec($this->_db, $sql, [$netid, $nameanno]);
        $result = $stmt->fetch();
        if ($throw) {
            if (!$result) {
                throw new Exception("Name does not match any annotations");
            }
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
    protected function insertNewAnnoSet($netid, $uid, $rootid, $annoname, $annotitle, $annoabstract) {
        // create starting annotations for the title, abstract and content        
        $nameparams = array('network_id' => $netid, 'user_id' => $uid, 'owner_id' => $uid,
            'root_id' => $rootid, 'parent_id' => $rootid, 'anno_level' => NC_NAME,
            'anno_text' => $annoname);
        $this->insertAnnoText($nameparams);
        // insert annotation for network title
        $titleparams = $nameparams;
        $titleparams['anno_text'] = $annotitle;
        $titleparams['anno_level'] = NC_TITLE;
        $this->insertAnnoText($titleparams);
        // insert annotation for network abstract        
        $descparams = $titleparams;
        $descparams['anno_text'] = $annoabstract;
        $descparams['anno_level'] = NC_ABSTRACT;
        $this->insertAnnoText($descparams);
        // insert annotation for network content (more than an abstract)
        $contentparams = $descparams;
        $contentparams['anno_level'] = NC_CONTENT;
        $this->insertAnnoText($contentparams);
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
                if (!in_array($keys[$i], $array)) {
                    $missing .= " " . $keys[$i];
                }
            }
            throw new Exception("Missing keys: $missing");
        }

        return $result;
    }

}

?>
