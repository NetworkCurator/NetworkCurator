<?php

/**
 * Class handling requests for user accounts (new user, change permissions, etc.)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCUsers {

    // db connection and array of parameters
    private $_conn;
    private $_params;
    // some variables extracted from $_params, for convenience    
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
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
        }        
    }

    /**
     * Creates a user in the database. 
     * 
     * @return boolean
     * @throws Exception
     */
    public function createNewUser() {

        // check that all parameters exist
        $pp = (array) $this->_params;
        $tocheck = array('firstname', 'middlename', 'lastname', 'email',
            'target_id', 'target_password');
        if (count(array_intersect_key(array_flip($tocheck), $pp)) !== count($tocheck)) {
            throw new Exception("Missing parameters");
        }

        //shorthand variables
        $firstname = $this->_params['firstname'];
        $middlename = $this->_params['middlename'];
        $lastname = $this->_params['lastname'];
        $email = $this->_params['email'];
        $targetid = $this->_params['target_id'];
        $targetpwd = md5($this->_params['target_password']);
        $uid = $this->_uid;
        $conn = $this->_conn;

        // perform tests on whether this user can create new network?
        if ($uid !== "admin") {
            throw new Exception("Insufficient permissions to create a network");
        }
       
        // check that the network does not already exist?                  
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . " 
            WHERE user_id='$targetid'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Failed checking user_id: " . mysqli_error($conn));
        }
        if (mysqli_num_rows($sqlresult) > 0) {
            echo "user already exists?";
            return false;
        }

        // if reached here, create the new user         
        $targetext = md5(makeRandomHexString(32));
        $sql = "INSERT INTO " . NC_TABLE_USERS . "
                   (datetime, user_id, user_firstname, user_middlename, user_lastname, 
                   user_email, user_pwd, user_extpwd, user_status) VALUES 
                   (UTC_TIMESTAMP(), '$targetid', '$firstname',
                  '$middlename', '$lastname', '$email', 
                  '$targetpwd', '$targetext', 1)";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed creating user: " . mysqli_error($conn));
        }

        // log the activity
        $fullname = $firstname;
        if ($middlename !== "") {
            $fullname .= " " . $middlename;
        }
        $fullname .= " " . $lastname;
        $logger = new NCLogger($conn);
        $logger->logActivity($uid, '', "created user account", $targetid, $fullname);

        return true;
    }

    /**
     * Checks a userid and password combination (used during log-in)
     * 
     * The params['user_id'] field is here expected to contain "guest"
     * The function looks at a target user id and password encoded 
     * in 'target' and 'password' components of $_params.
     * 
     * @return array
     * 
     * gives information about the user (firstname, lastname, etc.)
     * 
     * @throws Exception
     * 
     */
    public function verify() {

        // obtain the target user and password from the parameters
        $tid = addslashes(stripslashes($this->_params['target_id']));
        $tpw = md5($this->_params['target_password']);

        // look up the user in the database
        $sql = "SELECT user_id, user_extpwd, user_firstname, user_middlename, 
            user_lastname 
            FROM " . NC_TABLE_USERS . "
            WHERE BINARY user_id = '$tid' AND user_pwd='$tpw'";
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Invalid user id or password");
        }
        $ans = mysqli_fetch_assoc($sqlresult);

        return $ans;
    }

    /**
     * A validation of a user log-in status using the ext password code
     * 
     * Expects $_params to have the following components
     * user_id - user id
     * user_extpwd - user password (extpwd)
     * 
     * @return boolean
     * 
     * true if the user is logged in
     * false if the user is a guest
     *
     * @throws Exception
     */
    public function confirm() {

        // shorthand variables
        $uid = $this->_uid;
        $upw = $this->_params['user_extpwd'];

        // first check if the user is a guest
        if ($uid === "guest" && $upw === "guest") {
            return true;
        }

        if (!get_magic_quotes_gpc()) {
            $uid = addslashes($uid);
        }

        // Verify that user is in database         
        $sql = "SELECT user_extpwd FROM " . NC_TABLE_USERS . " 
            WHERE BINARY user_id = '$uid'";
        $sqlresult = mysqli_query($this->_conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Incorrect userid");
        }

        // Retrieve password from result. Validate if correct
        $row = mysqli_fetch_array($sqlresult);
        if ($upw == stripslashes($row['user_extpwd'])) {
            return true;
        } else {
            throw new Exception("Incorrect password");
        }
    }

    /**
     * Update the permission code on a network/user pair
     * 
     * @return boolean
     * 
     * true if the update was successful
     * 
     * @throws Exception
     */
    public function updatePermissions() {

        // the asking user should be the site administrator
        $uid = $this->_uid;
        $conn = $this->_conn;

        $targetid = $this->_params['target_id'];
        $targetnetwork = $this->_params['network_name'];
        $newpermissions = (int) $this->_params['permissions'];

        // get the netid that matches the network name
        $sql = "SELECT network_id FROM " . NC_TABLE_NETWORKS . "
            WHERE BINARY network_name = '$targetnetwork'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            $netid = "";
        } else {
            $sqlrow = mysqli_fetch_assoc($sqlresult);
            $netid = $sqlrow['network_id'];
        }

        // get permissions for the asking user, only curators and admin can 
        // update permissions
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS . "
            WHERE BINARY user_id = '$uid' AND network_id='$netid'
                AND permissions>3";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Insufficient permissions");
        }

        // make sure the target user exists and the permisions are valid
        if ($newpermissions < 0 || $newpermissions > 4) {
            throw new Exception("Invalid permission code $newpermissions");
        }
        if ($targetid == "guest" && $newpermissions > 1) {
            throw new Exception("Guest user cannot have high permissions");
        }
        $sql = "SELECT user_id, user_firstname, user_middlename, user_lastname 
            FROM " . NC_TABLE_USERS . "
            WHERE BINARY user_id = '$targetid'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Target user does not exist");
        }
        $userinfo = array();
        $userinfo[$targetid] = mysqli_fetch_assoc($sqlresult);
        $userinfo[$targetid]['permissions'] = $newpermissions;

        // make sure the new permission is different from the existing value
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS . "
            WHERE BINARY network_id = '$netid' AND user_id='$targetid'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Error querying existing permission level");
        }
        $sqlrow = mysqli_fetch_assoc($sqlresult);
        $oldpermissions = $sqlrow['permissions'];
        if ($oldpermissions == $newpermissions) {
            throw new Exception("Permissions do not need updating");
        }

        // if reached here, all is well. Update the permissions code                
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . " 
            (user_id, network_id, permissions) VALUES 
            ('$targetid', '$netid', $newpermissions) 
                ON DUPLICATE KEY UPDATE permissions='$newpermissions'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Error updating network permissions");
        }

        // log the activity   
        $logger = new NCLogger($conn);
        $logger->logActivity($uid, $netid, "updated permissions for user", $targetid, $newpermissions);

        return $userinfo;
    }

    /**
     * Lookup permission level for network/user combination
     * 
     * @return boolean
     * 
     * @throws Exception
     */
    public function queryPermissions() {

        $uid = $this->_uid;
        $conn = $this->_conn;
        $targetid = $this->_params['target_id'];
        $targetnetwork = $this->_params['network_name'];
        $tp = "" . NC_TABLE_PERMISSIONS;
        $tn = "" . NC_TABLE_NETWORKS;

        // the asking user should be the site administrator
        // a user asking for their own permissions, or curator on a network
        if ($uid !== "admin" && $uid !== $targetid) {
            $sql = "SELECT permissions FROM $tp JOIN $tn
                ON $tn.network_id=$tp.network_id 
            WHERE user_id='$uid' AND network_name='$targetnetwork' AND
               permissions>3";
            $sqlresult = mysqli_query($conn, $sql);            
            if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
                throw new Exception("Insufficient permissions spsps");
            }
        }

        // make sure the target user and network exist
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . "
            WHERE BINARY user_id = '$targetid'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Target user does not exist");
        }
        $sql = "SELECT network_id FROM " . NC_TABLE_NETWORKS . "
            WHERE BINARY network_name = '$targetnetwork'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult || (mysqli_num_rows($sqlresult) < 1)) {
            throw new Exception("Target network does not exist");
        }
        $sqlrow = mysqli_fetch_assoc($sqlresult);
        $netid = $sqlrow['network_id'];

        // if reached here, all is well. Get the permission level
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS . " 
            WHERE user_id='$targetid' AND network_id='$netid'";
        $sqlresult = mysqli_query($conn, $sql);
        if (!$sqlresult) {
            throw new Exception("Error querying network permissions");
        }
        if (mysqli_num_rows($sqlresult) < 1) {
            return 0;
        } else {
            $sqlrow = mysqli_fetch_assoc($sqlresult);
            return (int) $sqlrow['permissions'];
        }

        return true;
    }

    public function changePassword() {
        $ans = "changePassword";
        echo "echo $ans";
        return $ans;
    }

}

?>
