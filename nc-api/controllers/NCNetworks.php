<?php

/**
 * Class handling requests for networks (list networks, add a new network, etc.)
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */
class NCNetworks {

    // generic
    private $_conn;
    private $_params;
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_uid;

    /**
     * Constructor 
     * 
     * @param type $conn
     * 
     * Connection to the NC database
     * 
     * @param type $params
     * 
     * array with parameters
     */
    public function __construct($conn, $params) {

        $this->_conn = $conn;
        $this->_params = $params;

        // make parameters SQL-safe
        foreach ($params as $key => $value) {
            $this->_params[$key] = addslashes(stripslashes($value));
        }

        // check for required parameters
        if (isset($params['network_name'])) {
            $this->_network = $this->_params['network_name'];
        } else {
            $this->_network = "";
        }
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
        }
        if (!isset($params['user_extpwd'])) {
            throw new Exception("NCNetworks requires parameter user_extpwd");
        }

        // confirm the user
        $usercontroller = new NCUsers($conn, $this->_params);
        if (!$usercontroller->confirm()) {
            throw new Exception("Failed user confirmation");
        }
    }

    /**
     * 
     * Create a new network in the database. 
     *
     * The function requires the invoking user to be "admin"
     * The new network name should be in $param['networkname']
     * 
     * * This includes:
     *    a new entry in _networks
     *    a new directory in the nc-data directory on the file system
     *    a log entry in the _activity 
     * 
     * @return boolean
     * 
     * false if the requested network name already exists
     * true if the network is successfully created
     * 
     * @throws Exception
     *       
     */
    public function createNewNetwork() {

        // check that required parameters exist
        $pp = (array) $this->_params;
        $tocheck = array('network_name', 'network_title', 'network_desc');
        if (count(array_intersect_key(array_flip($tocheck), $pp)) !== count($tocheck)) {
            throw new Exception("Missing parameters");
        }

        // shorthand variables
        $networktitle = $this->_params['network_title'];
        $networkdesc = $this->_params['network_desc'];
        $conn = $this->_conn;
        $uid = $this->_uid;
        $network = $this->_network;

        // perform tests on whether this user can create new network?
        if ($uid !== "admin") {
            throw new Exception("Insufficient permissions to create a network");
        }

        // check that the network does not already exist?                  
        $sql = "SELECT network_name FROM " . NC_TABLE_NETWORKS . " 
            WHERE network_name='$network'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed checking networkname: " . mysqli_error($conn));
        }
        if (mysqli_num_rows($sqlresult) > 0) {
            return false;
        }

        // if reached here, create the new network in 5 steps
        // 1/5, find a new ids for the network and annotations 
        $netid = makeRandomID($conn, NC_TABLE_NETWORKS, 'network_id', 'W', NC_ID_LEN);
        $titleid = makeRandomID($conn, NC_TABLE_ANNO, 'anno_id', 'A', NC_ID_LEN);
        $descid = makeRandomID($conn, NC_TABLE_ANNO, 'anno_id', 'A', NC_ID_LEN);
        while ($descid == $titleid) {
            $descid = makeRandomID($conn, NC_TABLE_ANNO, 'anno_id', 'A', NC_ID_LEN);
        }

        // 2/5, create a directory on the server for the network        
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/" . $network;
        if (!mkdir($networkdir, 0777, true)) {
            throw new Exception("Failed creating network data space: " . $networkdir);
        }

        // 3/5, insert a new row into the networks table and annotations       
        $sql = "INSERT INTO " . NC_TABLE_NETWORKS . "
                   (network_id, network_name, owner_id) VALUES 
                   ('$netid', '$network', '$uid')";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed creating network: " . mysqli_error($conn));
        }
        $sql = "INSERT INTO " . NC_TABLE_ANNO . "
                   (datetime, network_id, user_id, root_id, parent_id, 
                   anno_id, anno_text, anno_depth, anno_status) VALUES 
                   (UTC_TIMESTAMP(), '$netid', '$uid', '$netid', '$netid', 
                   '$titleid', '$networktitle', -1, 1), 
                   (UTC_TIMESTAMP(), '$netid', '$uid', '$netid', '$netid', 
                   '$descid', '$networkdesc', -2, 1)";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed creating network annotations: $sql " . mysqli_error($conn));
        }

        // 4/5, create permissions for admin and guest
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . "
                   (user_id, network_id, permissions) VALUES 
                   ('admin', '$netid', '1000'), ('guest', '$netid', 0)";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed assigning network permissions: " . mysqli_error($conn));
        }

        // 5/5, log the activity
        $logger = new NCLogger($conn);
        $logger->logActivity($uid, '', "created network", $network, $networktitle);

        return true;
    }

    /**
     * Checks if a network with given name is public or not
     * 
     * @return boolean
     * 
     * true if guest account has permission>0 on the network
     * 
     * @throws Exception
     * 
     * 
     */
    public function isPublic() {
        $network = $this->_network;
        $guestpermissions = $this->getUserPermissions($network, "guest");
        if ($guestpermissions == 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Provides an array with network meta-data that is viewable by the user
     * 
     * The function checks the _networks for network name and id,
     * then checks _permissions to get networks that the user can see,
     * then checks _annotations to get network title and description
     * 
     * The operation getting title and description requires pivoting the 
     * annotations table. Also, this part requires only one network_id, anno_id,
     * depth=-1, status=1 entry. Similar condition for depth=-2.
     * 
     * @return array
     * 
     * @throws Exception
     * 
     */
    public function listNetworks() {

        $uid = $this->_uid;
        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $ta = "" . NC_TABLE_ANNO;

        $sql = "SELECT network_name, network_id, 
            GROUP_CONCAT(title SEPARATOR '') AS title,
            GROUP_CONCAT(description SEPARATOR '') AS description            
            FROM (SELECT network_name, $tn.network_id AS network_id,
            (CASE WHEN $ta.anno_depth='-1' THEN $ta.anno_text ELSE '' END) AS 'title',
            (CASE WHEN $ta.anno_depth='-2' THEN $ta.anno_text ELSE '' END) AS 'description'
                 FROM $tn 
                 JOIN $tp ON $tn.network_id=$tp.network_id 
                 JOIN $ta ON $tn.network_id=$ta.network_id 
                     WHERE BINARY $tp.user_id='$uid' AND $tp.permissions>0                 
                     AND $ta.anno_status=1 AND $ta.anno_depth<0
                 GROUP BY $ta.network_id, $ta.anno_depth) AS T GROUP BY network_name";
        //return $sql;
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed network list lookup");
        }
        $ans = array();
        while ($row = mysqli_fetch_assoc($sqlresult)) {
            $ans[] = $row;
        }
        return $ans;
    }

    /**
     * Get information about all users allowed to interact with a network
     * 
     * @return type
     * @throws Exception
     */
    public function listNetworkUsers() {

        $tu = "" . NC_TABLE_USERS;
        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $network = $this->_network;
        $uid = $this->_uid;

        // check that requesting user can view this network
        $upermissions = $this->getUserPermissions($network, $uid);
        if ($upermissions < 1) {
            throw new Exception("Insufficient permission to view network");
        }

        // get the users participating in this network       
        $sql = "SELECT user_firstname, user_middlename, user_lastname, 
            $tu.user_id AS user_id,
            network_name, $tn.network_id, permissions            
            FROM $tn 
               JOIN $tp ON $tn.network_id=$tp.network_id 
               JOIN $tu ON $tp.user_id=$tu.user_id                                
                  WHERE BINARY $tp.user_id!='admin' AND $tp.user_id!='guest'
                      AND $tn.network_name='$network' AND $tp.permissions>0
               ORDER BY $tu.user_id";
        //return $sql;
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed network list lookup");
        }
        $ans = array();
        while ($row = mysqli_fetch_assoc($sqlresult)) {
            $ans[] = $row;
        }
        return $ans;
    }

    /**
     * For a given network, fetches title, description and arrays
     * with curators, authors, and commentators
     * 
     * @return array
     * @throws Exception
     */
    public function getNetworkMetadata() {

        $uid = $this->_uid;
        $network = $this->_network;
        if ($network == "") {
            throw new Exception("Unspecified network");
        }

        // check if user has permission to view the table
        if ($this->getUserPermissions($network, $uid) < 1) {
            throw new Exception("Insufficient permission to view the network");
        }

        $tn = "" . NC_TABLE_NETWORKS;
        $tu = "" . NC_TABLE_USERS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $ta = "" . NC_TABLE_ANNO;

        // find the network id that corresponds to the name
        $sql = "SELECT network_id FROM $tn WHERE network_name='$network'";
        $sqlresult = mysqli_query($this->_conn, $sql);
        $row = mysqli_fetch_assoc($sqlresult);
        $netid = $row['network_id'];

        // find the title and abstract
        $sql = "SELECT network_id, 
            GROUP_CONCAT(title SEPARATOR '') AS title,
            GROUP_CONCAT(description SEPARATOR '') AS description            
            FROM (SELECT $ta.network_id AS network_id,
            (CASE WHEN $ta.anno_depth='-1' THEN $ta.anno_text ELSE '' END) AS 'title',
            (CASE WHEN $ta.anno_depth='-2' THEN $ta.anno_text ELSE '' END) AS 'description'
                 FROM $ta WHERE BINARY $ta.root_id='$netid' 
                     AND $ta.anno_status=1 AND $ta.anno_depth<0
                 GROUP BY $ta.anno_depth) AS T";
        //return $sql;
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed network list lookup");
        }
        $ans = mysqli_fetch_assoc($sqlresult);
        
        $ans['network_name'] =$network;
        
        // find the users who are curators on the network
        $sql = "SELECT user_firstname, user_middlename, user_lastname, 
            $tp.user_id, permissions 
            FROM $tp JOIN $tu ON $tp.user_id=$tu.user_id
                WHERE $tp.network_id='$netid' AND $tp.permissions>1
                AND $tp.permissions<10 
                ORDER BY $tu.user_lastname, $tu.user_firstname, $tu.user_middlename";
        //return "<br/><br/>".$sql;
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed user contribution lookup");
        }
        // move information from sql result into three new arrays by permission level
        $curators = array();
        $authors = array();
        $commentators = array();
        while ($row = mysqli_fetch_assoc($sqlresult)) {
            if ($row['permissions']==4) {
                $curators[] = $row;
            } else if ($row['permissions']==3) {
                $authors[] = $row;
            } else if ($row['permissions']==2) {
                $commentators[] = $row;
            }
        }
        // attach the new arrays to the answer object
        $ans['curators'] = $curators;
        $ans['authors'] = $authors;
        $ans['commentators'] = $commentators;
        
        return $ans;
    }

    /**
     * looks up the permission code for a user on a network.
     *
     * This function takes network and uid separately from the class _network
     * and _uid. This allows, for example, the admin user to get the 
     * permission for the guest user. 
     * 
     * @param type $network
     * @param type $uid
     * @throws Exception
     */
    private function getUserPermissions($network, $uid) {

        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;

        $sql = "SELECT permissions            
            FROM $tp JOIN $tn ON $tp.network_id=$tn.network_id                
            WHERE BINARY $tp.user_id='$uid' AND $tn.network_name='$network'";
        //return $sql;
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed checking network permissions");
        }
        if (mysqli_num_rows($sqlresult) == 0) {
            return 0;
        } else {
            $row = mysqli_fetch_assoc($sqlresult);
            return $row['permissions'];
        }
    }

}

?>
