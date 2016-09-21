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
    protected $_uid; // user_id (or guest)
    protected $_upw; // user_confirmation code (or guest)
    private $_log = true;

    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */
    public function __construct($db, $params) {
        parent::__construct($db);
        $this->_params = $params;
        if (isset($params['user_id'])) {
            $this->_uid = $params['user_id'];
        } else {
            throw new Exception("Missing required parameter user_id");
        }
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
     * @param int $n
     * 
     * 
     * @return int or array
     * 
     * when $n=1, the return value is just one object
     * when $n>1, the returned object is an array of viable ids
     * 
     * integer, number of hex digits in the random id (excluding prefix)
     * 
     */
    protected function makeRandomID($dbtable, $idcolumn, $idprefix, $stringlength, $n = 1) {
        $keeplooking = true;
        while ($keeplooking) {
            // create a batch of new ids
            //$newids = array_pad([""], $n, "");
            //for ($i = 0; $i < $n; $i++) {
            //    $newids[$i] = $idprefix . makeRandomString($stringlength);
            //}
            //$whereids = implode("%' OR WHERE $idcolumn LIKE '$idprefix", $newids);
            //$whereclause = " WHERE $icolumn LIKE '$idprefix" . $whereids . "'";
            
            $newid = $idprefix . makeRandomString($stringlength);
            $sql = "SELECT $idcolumn FROM $dbtable WHERE $idcolumn='$newid'";
            $keeplooking = $this->_db->query($sql)->fetch();
            
            //$sql = "SELECT $idcolumn FROM $dbtable $whereclause";
        }
        return $newid;
        //if ($n>1) {
        //    return $newids;
        //} else {
        //    return $newids[0];
        //}        
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
        if ($this->_log) {
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
        }
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
        if ($this->_log) {
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
     * network_id, owner_id, user_id, root_id, parent_id, anno_text, anno_type
     * 
     * @return 
     * 
     * id code for the new annotation
     * 
     */
    public function insertAnnoText($params) {

        // get a new annotation id
        $tat = "" . NC_TABLE_ANNOTEXT;
        $newid = $this->makeRandomID($tat, 'anno_id', NC_PREFIX_TEXT, NC_ID_LEN);
        $params['anno_id'] = $newid

                ;

        // insert the annotation into the table
        $sql = "INSERT INTO $tat
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                    :anno_id, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        $this->qPE($sql, $params);

        return $newid;
    }

    /**
     * Updates the annotext table with some new data.
     * Updating means changing an existing row with anno_id to status=OLD,
     * and inserting a new row with status = active. 
     * 
     * @param array $params
     * 
     * The function assumes the array contains exactly these elements.
     * network_id, user_id, root_id, parent_id, anno_id, anno_text, anno_type
     * 
     * @return
     * 
     * integer 1 upon success
     *      
     */
    protected function updateAnnoText($params) {

        $params = $this->subsetArray($params, ["network_id", "datetime", "owner_id",
            "root_id", "parent_id", "anno_id", "anno_text", "anno_type"]);
        $params['user_id'] = $this

            ->_uid   ;

        $tat =  "

            "  . NC_TABLE_ANNOTEXT;
            
        // set existing annotions as OLD, This becomes the historical record
        $sql = "UPDATE $tat SET anno_status = " . NC_OLD . "
                    WHERE network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $this->qPE($sql, [$params['network_id'], $params['anno_id']]);

        // insert a new  copy. This becomes the current version
        $sql  = "INSERT INTO $tat
                    (datetime, modified, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (:datetime, UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                    :anno_id, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        $this->qPE($sql, $params);
        
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
                " WHERE BINARY user_id = ? AND network_id = ?";
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
     * A network name like 

             "my- network"
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
            return "";
        } else {
            return $result['network_id'];
        }
    }

    /**
     * Get the root id associated with a given name-level annotation.
     * E.g. if we know a class name is "GOOD_NODE

             ", this function will
     * return the "Cxxxxxx " code associated with this class name.
     * 
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
                    anno_type = " . NC_NAME . " AND anno_status != " . NC_OLD;
        $stmt = $this->qPE($sql, [$netid, $nameanno]);
        $result = $stmt->fetch();
        if ($throw) {
            if (!$result) {
                throw new Exception("Name '$nameanno' does not match any annotations");
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
    protected function getAnnoId($netid, $rootid, $level) {
        if ($level != NC_ABSTRACT && $level == NC_TITLE && $level == NC_CONTENT) {
            throw new Exception("Function only suitable for title, abstract, content");
        }
        $sql = "SELECT anno_id FROM " . NC_TABLE_ANNOTEXT . "
                    WHERE BINARY network_id = ? AND root_id = ? AND
                    anno_type = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$netid, $rootid, $level]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching anno id");
        }
        return $result['anno_id'];
    }
   
    /**
     * New way of inserting a set of annotation that does not use anno_id lookup
     * and uses only on insert statement.
     * 
     * @param type $netid
     * @param type 

              $uid
     * @param type  $rootid
     * @param type $annoname
     * @param type $annotitle
     * @param type $annoabstract
     * @param type $annocontent
     * 
     */
    protected function insertNewAnnoSet($netid, $uid, $rootid, $annoname, $annotitle, $annoabstract = 'empty', $annocontent = 'empty') {

        $annoid = $this->makeRandomID(NC_TABLE_ANNOTEXT, "anno_id", "T", NC_ID_LEN - 1);

        //echo $annoid."\n";
        
        // create an array of parameter values (one set for name, abstrat, title, content)
        $params = [];
        foreach (["A", "B", "C", "D"] as $abc) {
            $params['network_id'  . $abc

              ] = $netid ;
            $params['owner_id' . $abc

                  ] =    $uid;
            $params['user_id' . $abc] = $uid;
            $params['root_id' . $abc] = $rootid;
            $params['parent_id' . $abc] = $rootid;
        }
        $params['anno_idA'] = $annoid . NC_NAME;
        $params['anno_idB'] = $annoid . NC_TITLE;
        $params['anno_idC'] = $annoid . NC_ABSTRACT;
        $params['anno_idD'] = $annoid . NC_CONTENT;
        $params['anno_textA'] = $annoname;
        $params['anno_textB'] = $annotitle;
        $params['anno_textC'] = $annoabstract;
        $params['anno_textD'] = $annocontent;

        $sql = "INSERT INTO " . NC_TABLE_ANNOTEXT . "
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (UTC_TIMESTAMP(), :network_idA, :owner_idA, :user_idA, :root_idA, :parent_idA,
                    :anno_idA, :anno_textA, " . NC_NAME . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idB, :owner_idB, :user_idB, :root_idB, :parent_idB,
                    :anno_idB, :anno_textB, " . NC_TITLE . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idC, :owner_idC, :user_idC, :root_idC, :parent_idC,
                    :anno_idC, :anno_textC, " . NC_ABSTRACT . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idD, :owner_idD, :user_idD, :root_idD, :parent_idD,
                    :anno_idD, :anno_textD, " . NC_CONTENT . ", " . NC_ACTIVE . " ) ";

        $this->qPE($sql , $params);
    }

    /**
     * Get a small array using only a few elements from a larger (assoc) array
     * 
     * @param array $array
     * @param array $keys

            
     * @return array
     * 
     */
    protected function subsetArray($array , $keys) {

        // perform the subset
        $result = array_intersect_key($array , array_flip($keys));

        // check if all the keys were found
        if (count($result) !== count($keys)) {
            // some of the keys are missing, make a string with a summary
            $missing = "";
            for ($i = 0;  $i < count($keys);  $i++) {
                if (!array_key_exists($keys[$i] , $array)) {
                    $missing .= " " . $keys[$i];
                }
            }
            throw new Exception("Missing keys: $missing");
        }

        return $result;
    }

}

?>
