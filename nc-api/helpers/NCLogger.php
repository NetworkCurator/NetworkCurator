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

    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */
    public function __construct($db, $_params) {
        $this->_db = $db;
        $this->_params = $params;
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
    function makeRandomID($dbtable, $idcolumn, $idprefix, $stringlength) {
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
     * The action insert two rows. One is automatically set as OLD and servers
     * for history tracking only. One row is set at active and is intended 
     * when looking up the current version.
     * 
     * @param type $params
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
                   (UTC_TIMESTAMP(), :network_idA, :owner_idA, :user_idA, :root_idA, :parent_idA,
                   :anno_idA, :anno_textA, :anno_levelA, " . NC_ACTIVE . "),        
                   (UTC_TIMESTAMP(), :network_idB, :owner_idB, :user_idB, :root_idB, :parent_idB,
                   :anno_idB, :anno_textB, :anno_levelB, " . NC_OLD . ")";
        $stmt = $this->_db->prepare($sql);
        // get a new array with just the parameters that are needed
        $keys = ['network_id', 'owner_id', 'user_id', 'root_id', 'parent_id',
            'anno_id', 'anno_text', 'anno_level'];
        $pp = array();
        for ($i = 0; $i < count($keys); $i++) {
            $nowkey = $keys[$i];
            $pp[$nowkey . "A"] = $params[$nowkey];
            $pp[$nowkey . "B"] = $params[$nowkey];
        }
        $stmt->execute($pp);
        
        return 1;
    }

    /**
     * Updates the annotext table with some new data.
     * Updating means changing the active "status=1" entry, and adding
     * a "status=0" entry for historical records.
     * 
     * 
     * 
     * @param array $params
     * 
     * The function assumes the array contains seven element.
     * 
     * @return
     * 
     * integer 1 upon success
     * 
     */
    public function updateAnnoText($params) {

        $tat = "" . NC_TABLE_ANNOTEXT;
               
        // prepare a statement setting all anno_status to disabled for a given anno_id
        $sql = "UPDATE $tat SET 
                          datetime = UTC_TIMESTAMP(), user_id = :user_id,
                          root_id = :root_id, parent_id = :parent_id,
                          anno_text = :anno_text
                WHERE network_id = :network_id AND anno_id = :anno_id 
                   AND owner_id = :owner_id 
                   AND anno_level= :anno_level AND anno_status=" . NC_ACTIVE;
        // the update statement does not change the original user_id, so 
        // here $pp is a subset of $params        
        $stmt = prepexec($this->_db, $sql, $params);

        // insert an extra copy for historical records
        $sql = "INSERT INTO $tat 
                   (datetime, network_id, owner_id, user_id, root_id, parent_id, 
                   anno_id, anno_text, anno_level, anno_status) VALUES                          
                   (UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                   :anno_id, :anno_text, :anno_level, " . NC_OLD . ")";        
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
    public function getUserPermissions($network, $uid) {
        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $sql = "SELECT permissions            
            FROM $tp JOIN $tn ON $tp.network_id = $tn.network_id                
            WHERE BINARY $tp.user_id = ? AND $tn.network_name = ?";
        $stmt = $this->_db->prepare($sql);
        $stmt = $stmt->execute(array($uid, $network));
        $result = $stmt . fetch();
        return $result[permissions];
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
    public function getUserPermissionsNetID($netid, $uid) {
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS .
                " WHERE BINARY user_id = ? AND network_id = ?";
        $stmt = prepexec($this->_db, $sql, [$uid, $netid]);
        $result = $stmt->fetch();
        return $result['permissions'];
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
     * @return string
     * 
     * A code used in the db, e.g. "W0123456789".
     * If a network does nto exists, return the empty string.
     *  
     */
    public function getNetworkId($netname) {
        //echo "getNetworkID\n";
        $sql = "SELECT network_id FROM " . NC_TABLE_ANNOTEXT . "
            WHERE BINARY anno_text = ? AND anno_level = " . NC_NAME . " 
                AND anno_status = 1";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute([$netname]);
        $result = $stmt->fetch();
        if (!$result) {
            return "";
        } else {
            return $result['network_id'];
        }
    }

}

?>
