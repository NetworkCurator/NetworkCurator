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
    protected $_params; // parameters passed on by the user
    protected $_uid; // user_id (or guest)
    protected $_upw; // user_confirmation code (or guest)
    //private $_log = true;
    // some constant for getting annotations types
    protected $_annotypes = ["name" => NC_NAME, "title" => NC_TITLE, "abstract" => NC_ABSTRACT, "content" => NC_CONTENT];

    //protected $_annovals = [NC_NAME, NC_TITLE, NC_ABSTRACT, NC_CONTENT];

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
    //public function setLogging($tolog) {
    //    $this->_log = $tolog;
    //}

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
     * @return
     */
    protected function makeRandomID($dbtable, $idcolumn, $idprefix, $stringlength) {
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
     * 
     * @param type $dbtable
     * @param type $idcolumn
     * @param type $idprefix
     * @param type $stringlength     
     * 
     * @param array $n
     * 
     * number of requested viable random ids      
     * 
     * @return string
     */
    protected function makeRandomIDSet($dbtable, $idcolumn, $idprefix, $stringlength, $n) {
        $keeplooking = true;
        while ($keeplooking) {
            // create a batch of new ids
            $newids = array_pad([""], $n, "");
            for ($i = 0; $i < $n; $i++) {
                $newids[$i] = $idprefix . makeRandomString($stringlength);
            }
            // encode the ids into a query against the db
            $whereids = implode("%' OR $idcolumn LIKE '", $newids);
            $whereclause = " WHERE $idcolumn LIKE '" . $whereids . "%'";
            $sql = "SELECT $idcolumn FROM $dbtable $whereclause";
            //echo $sql."\n";
            $stmt = $this->qPE($sql, []);
            //echo "sososos ";
            $keeplooking = $stmt->fetch();
            //echo "aakaka "; 
        }
        //echo "inhere ";
        //echo implode(", ", $newids);
        return $newids;
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
        //if ($this->_log) {
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
        //}
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
        //if ($this->_log) {
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
        //}
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
        $params['anno_id'] = $newid;

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
        $params['user_id'] = $this->_uid;

        $tat = "" . NC_TABLE_ANNOTEXT;

        // set existing annotions as OLD, This becomes the historical record
        $sql = "UPDATE $tat SET anno_status = " . NC_OLD . "
                    WHERE network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $this->qPE($sql, [$params['network_id'], $params['anno_id']]);

        // insert a new  copy. This becomes the current version
        $sql = "INSERT INTO $tat
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
        throw new Exception("getUserPermission with network as string - deprecated?");
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

    protected function getFullSummaryFromRootId($netid, $rootid, $throw = true) {
 
        // fetch all the summary annotations for a given root (without pivoting)                        
        $sql = "SELECT network_id, datetime, owner_id, root_id, parent_id, 
            anno_id, anno_type, anno_text FROM " . NC_TABLE_ANNOTEXT . "  
                WHERE network_id = ? AND root_id = ?                  
                  AND anno_type <= " . NC_CONTENT . " AND anno_status=" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$netid, $rootid]);
        $result = [];
        while ($row = $stmt->fetch()) {
            switch ($row['anno_type']) {
                case NC_NAME:
                    $result['name'] = $row;
                    break;
                case NC_TITLE:
                    $result['title'] = $row;
                    break;
                case NC_ABSTRACT:
                    $result['abstract'] = $row;
                    break;
                case NC_CONTENT:
                    $result['content'] = $row;
                    break;
                default:
                    break;
            }
        }
        if (count($result) == 0) {
            if ($throw) {
                throw new Exception("Annotations for '$rootid' do not exist");
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

        throw new Exception("insertNewAnnoSet is deprecated. Use batchInsertAnnoSets instead");

        $annoid = $this->makeRandomID(NC_TABLE_ANNOTEXT, "anno_id", "T", NC_ID_LEN - 1);

        // create an array of parameter values (one set for name, abstrat, title, content)
        $params = [];
        foreach (["A", "B", "C", "D"] as $abc) {
            $params['network_id' . $abc
                    ] = $netid;
            $params['owner_id' . $abc
                    ] = $uid;
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

        $this->qPE($sql, $params);
    }

    /**
     * @param string $netid
     * 
     * network id code
     * 
     * @param array $annosets
     * 
     * a 2d array. The first index should be accessible by an integer interator. 
     * The elements accessed by the first index should be associative arrays with key:
     * 
     * 'name', 'title', 'abstract', 'content',
     * 
     * @param array $rootids
     * 
     * a 1d array that match the $annosets.
     */
    protected function batchInsertAnnoSets($netid, $annosets, $rootids) {

        //echo "bIAS 1 ";
        $n = count($annosets);
        if ($n > 999000) {
            throw new Exception("Too many annotations at once");
        }
        //echo "bIAS 2 ";
        $annoids = $this->makeRandomIDSet(NC_TABLE_ANNOTEXT, "anno_id", "T", NC_ID_LEN - 1, $n);
        //echo "bIAS 3 ";
        // start making the query
        $sql = "INSERT INTO " . NC_TABLE_ANNOTEXT . "
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES ";

        // prepare the secong part of the query 
        // this involves making an array of (..., ..., ...) sets and matching params
        // later this info will be concatenated and executed
        $sqlinsert = [];
        $params = [];
        $uid = $this->_uid;
        for ($i = 0; $i < $n; $i++) {
            // create a numeric string that will not be confused e.g. x1 and x12 both match x1.
            $x = sprintf("%'.06d", $i);
            // some shorthand to encode data that does not have to be prepped
            $ri = $rootids[$i];
            foreach ($this->_annotypes as $key => $val) {
                $nowid = $annoids[$i] . $val;
                $nowtext = $annosets[$i][$key];
                if (strlen($nowtext) < 2) {
                    $nowtext = $annosets[$i]['name'];
                }
                // create an entry with value sets for the sql statement
                $sqlinsert[] = "(UTC_TIMESTAMP(), '$netid', '$uid', '$uid', '$ri', '$ri',
                    '$nowid' , :anno_text_$val$x, $val, " . NC_ACTIVE . " )";
                $params["anno_text_$val$x"] = $nowtext;
            }
        }

        //print_r($params);
        //echo "bIAS 5 ";
        $sql .= implode(", ", $sqlinsert);
        //echo "bIAS 6 \n$sql \n";
        $this->qPE($sql, $params);
        //echo "bIAS 7 ";
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

}

?>
