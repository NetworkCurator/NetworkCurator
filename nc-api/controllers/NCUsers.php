<?php

$nowdir = dirname(__FILE__);
include_once $nowdir . "/../helpers/NCIdenticons.php";

/**
 * Class handling requests for user accounts (new user, change permissions, etc.)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCUsers extends NCLogger {
    // db connection and array of parameters are inherited from NCLogger    

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
    public function __construct($db, $params) {
        $this->_db = $db;
        $this->_params = $params;

        // check for required parameters       
        if (isset($params['user_id'])) {
            $this->_uid = $this->_params['user_id'];
        } else {
            throw new Exception("NCUsers requires parameter user_id");
        }
    }

    /**
     * Creates a user in the database. 
     * 
     * @return boolean
     * @throws Exception
     */
    public function createNewUser() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id",
            "target_firstname", "target_middlename", "target_lastname",
            "target_email", "target_id", "target_password"]);

        // create a hashes for the user password
        $targetpwd = password_hash($params['target_password'], PASSWORD_BCRYPT);
        $params['target_password'] = $targetpwd;

        // perform tests on whether this user can create new user?
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to create a user");
        }

        // check that the network does not already exist?                  
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . " WHERE user_id = ? ";
        //ncInterpolateQuery($sql, $params)
        $stmt = prepexec($this->_db, $sql, [$params['target_id']]);
        if ($stmt->fetchAll()) {
            throw new Exception("Target user id exists");
        }

        // if reached here, create the new user  
        // create a new external password code (for cookies)        
        $params['target_extpwd'] = md5(makeRandomHexString(32));

        // write the target user into the database
        $sql = "INSERT INTO " . NC_TABLE_USERS . "
                   (datetime, user_id, user_firstname, user_middlename, user_lastname, 
                   user_email, user_pwd, user_extpwd, user_status) VALUES 
                   (UTC_TIMESTAMP(), :target_id, :target_firstname,
                  :target_middlename, :target_lastname, :target_email, 
                  :target_password, :target_extpwd, 1)";
        $pp = $this->subsetArray($params, ['target_id',
            'target_firstname', 'target_middlename', 'target_lastname',
            'target_email', 'target_password', 'target_extpwd']);
        $stmt = prepexec($this->_db, $sql, $pp);

        // create a user identicon        
        $userimg = new NCIdenticons();
        $imgfile = dirname(__FILE__) . "/../../nc-data/users/" . $pp['target_id'] . ".png";
        imagepng($userimg->getIdenticon(), $imgfile);

        // log the activity        
        $fullname = ncFullname($this->_params, $prefix = "target_");
        $this->logActivity($this->_uid, '', "created user account", $this->_params['target_id'], $fullname);

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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["target_id", "target_password"]);

        // look up the user in the database
        $sql = "SELECT user_id, user_pwd, user_extpwd, user_firstname, 
            user_middlename, user_lastname 
            FROM " . NC_TABLE_USERS . " WHERE BINARY user_id = ? ";
        $stmt = prepexec($this->_db, $sql, [$params['target_id']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Invalid user id or password");
        }
        if (!password_verify($params['target_password'], $result['user_pwd'])) {
            throw new Exception("Invalid user id or password");
        }
        // erase the password hash from the output
        $result['user_pwd'] = '';

        return $result;
    }

    /**
     * A validation of a user log-in status using the ext password code
     * 
     * Expects $_params to have the following components     
     * user_extpwd - user password (extpwd)
     * 
     * @return boolean
     * 
     * true if the user is logged in or is a guest
     * throws exception if the confirmation does not match
     *
     * @throws Exception
     */
    public function confirm() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id", "user_extpwd"]);

        // first check if the user is a guest
        if ($this->_uid === "guest" && $params['user_extpwd'] === "guest") {
            return true;
        }

        // Verify that user is in database         
        $sql = "SELECT user_extpwd FROM " . NC_TABLE_USERS . " 
            WHERE BINARY user_id = ?";
        $stmt = prepexec($this->_db, $sql, [$this->_uid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Invalid user_id");
        }

        // Retrieve password from result. Validate if correct        
        if ($params['user_extpwd'] === $result['user_extpwd']) {
            return true;
        } else {
            throw new Exception("Incorrect confirmation code");
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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id",
            "target_id", "network_name", "permissions"]);

        if ($params['target_id'] == "admin") {
            throw new Exception("Cannot change permissions for admin, haha, nice try.");
        }

        $targetid = $params['target_id'];
        $newperm = (int) $params['permissions'];

        // get the netid that matches the network name
        $netid = $this->getNetworkId($params['network_name']);

        // get permissions for the asking user
        // only curators and admin can update permissions        
        $uperm = $this->getUserPermissionsNetID($netid, $this->_uid);
        if ($uperm < NC_PERM_CURATE) {
            throw new Exception("Insufficient permissions");
        }

        // make sure the target user exists and the permisions are valid
        if ($newperm < NC_PERM_NONE || $newperm > NC_PERM_CURATE) {
            throw new Exception("Invalid permission code $newperm");
        }
        if ($targetid == "guest" && $newperm > NC_PERM_VIEW) {
            throw new Exception("Guest user cannot have high permissions");
        }
        $sql = "SELECT user_id, user_firstname, user_middlename, user_lastname 
            FROM " . NC_TABLE_USERS . " WHERE BINARY user_id = ?";
        $stmt = prepexec($this->_db, $sql, [$targetid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Target user does not exist");
        }
        $userinfo = array();
        $userinfo[$targetid] = $result;
        $userinfo[$targetid]['permissions'] = $newperm;

        // make sure the new permission is different from the existing value
        $targetperm = $this->getUserPermissionsNetID($netid, $targetid);
        if ($targetperm === $newperm) {
            throw new Exception("Permissions do not need updating");
        }

        // if reached here, all is well. Update the permissions code                
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . " 
            (user_id, network_id, permissions) VALUES 
            (?, ?, ?) ON DUPLICATE KEY UPDATE permissions = ?";
        $stmt = prepexec($this->_db, $sql, [$targetid, $netid, $newperm, $newperm]);

        // log the activity           
        $this->logActivity($this->_uid, $netid, "updated permissions for user", $targetid, $newperm);

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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id",
            "target_id", "network_name"]);
        
        // get network id
        $netid = $this->getNetworkId($params['network_name']);

        // the asking user should be the site administrator
        // a user asking for their own permissions, or curator on a network
        if ($this->_uid != $params['target_id']) {
            $uperm = $this->getUserPermissionsNetID($netid, $this->_uid);
            if ($uperm < NC_PERM_CURATE) {
                throw new Exception("Insufficient permissions");
            }
        }

        // make sure the target user exist
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . "
            WHERE BINARY user_id = ?";
        $stmt = prepexec($this->_db, $sql, [$params['target_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Target user does not exist");
        }

        if ($netid === "") {
            throw new Exception("Target network does not exist");
        }

        // if reached here, all is well. Get the permission level
        $targetperm = $this->getUserPermissionsNetID($netid, $params['target_id']);
        return $targetperm;
    }

}

?>
