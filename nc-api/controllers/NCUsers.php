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
        parent::__construct($db, $params);
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
        $plainpwd = $params['target_password'];
        $targetpwd = password_hash($plainpwd, PASSWORD_BCRYPT);
        $params['target_password'] = $targetpwd;

        // perform tests on whether this user can create new user?
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to create a user");
        }

        // check the requested user_id is valide
        if (!$this->validateNameString($params['target_id'])) {
            throw new Exception("Invalid user name");
        }

        $this->dblock([NC_TABLE_USERS]);

        // check that the network does not already exist?                  
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . " WHERE user_id = ? ";
        $stmt = $this->qPE($sql, [$params['target_id']]);
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
        $stmt = $this->qPE($sql, $pp);

        $this->dbunlock();

        // create a user identicon        
        $userimg = new NCIdenticons();
        $imgfile = dirname(__FILE__) . "/../../nc-data/users/" . $pp['target_id'] . ".png";
        imagepng($userimg->getIdenticon(), $imgfile);

        // log the activity        
        $fullname = ncFullname($this->_params, $prefix = "target_");
        $this->logActivity($this->_uid, '', "created user account", $this->_params['target_id'], $fullname);
        $this->logAction($this->_uid, $this->_params['source_ip'], "NCUsers", "createNewUser", $this->_params['target_id'] . ": " . $fullname);

        // send a welcome email to the user
        $this->sendNewUserEmail($plainpwd);
        
        return true;
    }

    /**
     * Remove all data related to a user.
     * Can be invoked only by admin user (mainly for developer use)
     * 
     * @return string
     * 
     * message with purge summary
     * 
     */
    public function purgeUser() {

        // this action only for admin
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to purge user");
        }

        $params = $this->subsetArray($this->_params, ["target"]);
        $target = $params['target'];

        // check that the user exists
        // make sure the target user exist
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . " WHERE user_id = ?";
        $stmt = $this->qPE($sql, [$params['target']]);
        if (!$stmt->fetch()) {
            throw new Exception("Target user does not exist");
        }

        // proceed to purge the network
        // remove all entries from db tables
        $alltables = [NC_TABLE_ACTIVITY, NC_TABLE_PERMISSIONS, NC_TABLE_ANNOTEXT,
            NC_TABLE_FILES, NC_TABLE_PERMISSIONS, NC_TABLE_USERS, NC_TABLE_LOG];
        foreach ($alltables as $dbtable) {
            $sql = "DELETE FROM $dbtable WHERE user_id = ?";
            $this->qPE($sql, [$target]);
        }
        $alltables2 = [NC_TABLE_ANNOTEXT, NC_TABLE_NETWORKS];
        foreach ($alltables2 as $dbtable) {
            $sql = "DELETE FROM $dbtable WHERE owner_id = ?";
            $this->qPE($sql, [$target]);
        }

        // remove user data/profile img 
        $targetimg = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/users/" . $target . ".png";
        system("rm -f $targetimg");

        // record the action in the site log
        $this->logAction($this->_uid, $this->_params['source_ip'], "NCUsers", "purgeUser", $target);

        $result = "Removing user " . $target . ".\n";
        return $result;
    }

    /**
     * Update first/middle/last names, email, or password for an existing user
     * 
     * 
     */
    public function updateUserInfo() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, [
            "target_firstname", "target_middlename", "target_lastname",
            "target_email", "target_id", "target_password", "target_newpassword"]);

        // perform tests on whether this user can create new user?
        if ($this->_uid !== "admin" && $this->_uid !== $params['target_id']) {
            throw new Exception("Insufficient permissions to update user info");
        }

        // verify the current password
        $this->verify();

        // for good measure, update the external passord code (for cookies)        
        $params['target_extpwd'] = md5(makeRandomHexString(32));

        // update the non-password fields
        $sql = "UPDATE " . NC_TABLE_USERS . " SET user_firstname = ? , user_middlename = ? ,
                    user_lastname = ? , user_email = ?, user_extpwd = ? WHERE user_id = ? ";
        $stmt = $this->qPE($sql, [$params['target_firstname'], $params['target_middlename'],
            $params['target_lastname'], $params['target_email'], $params['target_extpwd'],
            $params['target_id']]);
        $result = "Updated user information";

        // check if to update the password
        if ($params['target_newpassword'] != "") {
            // create a hashes for the user password
            $targetpwd = password_hash($params['target_newpassword'], PASSWORD_BCRYPT);
            $sql = "UPDATE " . NC_TABLE_USERS . " SET user_pwd = ? WHERE user_id = ? ";
            $stmt = $this->qPE($sql, [$targetpwd, $params['target_id']]);
            $result .= " and password";
        }

        $result .= " (new login may be required)";
        return $result;
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
            FROM " . NC_TABLE_USERS . " WHERE user_id = ? ";
        $stmt = $this->qPE($sql, [$params['target_id']]);
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
        $stmt = $this->qPE($sql, [$this->_uid]);
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
            "target_id", "network", "permissions"]);

        if ($params['target_id'] == "admin") {
            throw new Exception("Cannot change permissions for admin");
        }

        $targetid = $params['target_id'];
        $newperm = (int) $params['permissions'];

        // get the netid that matches the network name
        $netid = $this->getNetworkId($params['network']);

        // get permissions for the asking user
        // only curators and admin can update permissions        
        $uperm = $this->getUserPermissions($netid, $this->_uid);
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
            FROM " . NC_TABLE_USERS . " WHERE user_id = ?";
        $stmt = $this->qPE($sql, [$targetid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Target user does not exist");
        }
        $userinfo = array();
        $userinfo[$targetid] = $result;
        $userinfo[$targetid]['permissions'] = $newperm;

        // make sure the new permission is different from the existing value
        $targetperm = $this->getUserPermissions($netid, $targetid);
        if ($targetperm === $newperm) {
            throw new Exception("Permissions do not need updating");
        }

        // if reached here, all is well. Update the permissions code                
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . " 
            (user_id, network_id, permissions) VALUES 
            (?, ?, ?) ON DUPLICATE KEY UPDATE permissions = ?";
        $stmt = $this->qPE($sql, [$targetid, $netid, $newperm, $newperm]);

        // log the activity           
        $this->logActivity($this->_uid, $netid, "updated permissions for user", $targetid, $newperm);
        $this->sendUpdatePermissionsEmail($netid);

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
        $params = $this->subsetArray($this->_params, ["target", "network"]);

        // get network id
        $netid = $this->getNetworkId($params['network']);

        // the asking user should be the site administrator
        // a user asking for their own permissions, or curator on a network
        if ($this->_uid != $params['target']) {
            $uperm = $this->getUserPermissions($netid, $this->_uid);
            if ($uperm < NC_PERM_CURATE) {
                throw new Exception("Insufficient permissions");
            }
        }

        // make sure the target user exist
        $sql = "SELECT user_id FROM " . NC_TABLE_USERS . " WHERE user_id = ?";
        $stmt = $this->qPE($sql, [$params['target']]);
        if (!$stmt->fetch()) {
            throw new Exception("Target user does not exist");
        }

        if ($netid === "") {
            throw new Exception("Target network does not exist");
        }

        // if reached here, all is well. Get the permission level
        $targetperm = $this->getUserPermissions($netid, $params['target']);
        return $targetperm;
    }

    /**
     * queries a user id  and return the name, email address, etc. 
     * 
     * @return type
     * @throws Exception
     */
    public function fetchUserInfo() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["target"]);

        // the asking user should be the site administrator
        // a user asking for their own permissions, or curator on a network
        if ($this->_uid != $params['target'] && $this->_uid != "admin") {
            throw new Exception("Insufficient permissions");
        }

        // make sure the target user exist
        $sql = "SELECT user_id, user_firstname, user_middlename, user_lastname, user_email 
            FROM " . NC_TABLE_USERS . " WHERE user_id = ?";
        $stmt = $this->qPE($sql, [$params['target']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Target user does not exist");
        }

        return $result;
    }

    /**
     * returns array with all user ids (only for admins)
     * 
     * @throws Exception
     * 
     */
    public function listUsers() {

        // the asking user should be the site administrator
        // a user asking for their own permissions, or curator on a network
        if ($this->_uid != "admin") {
            throw new Exception("Insufficient permissions");
        }

        $sql = "SELECT user_id AS id, user_firstname AS firstname, 
            user_middlename AS middlename, user_lastname AS lastname, user_status AS status
            FROM " . NC_TABLE_USERS;

        $stmt = $this->_db->query($sql);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    
    /**
     * Send an email about a new user
     */
    private function sendNewUserEmail($plainpwd) {        
        $ncemail = new NCEmail($this->_db);       
        $emaildata = ['PASSWORD' => $plainpwd, 'EMAIL' => $this->_params['target_email']];
        $ncemail->sendEmailToUsers("email-new-user", $emaildata, [$this->_params['target_id']]);        
    }
    
    /**
     * Send an email about a new graph object
     * 
     * @param string $netid
     * 
     * network id code (it is not automatically determined in the constructor in NCUsers)
     * 
     */
    private function sendUpdatePermissionsEmail($netid) {

        $ncemail = new NCEmail($this->_db);

        // prepare email fillers (for now set PERMISSIONS to a dummy value)
        $emaildata = ['NETWORK' => $this->_params['network'],
            'TARGETID' => $this->_params['target_id'],
            'PERMISSIONS' => 'none', 'USER' => $this->_uid];

        // set the real PERMISSIONS text
        $permcodes = ["curate" => NC_PERM_CURATE, "edit" => NC_PERM_EDIT, "comment" => NC_PERM_COMMENT,
            "view" => NC_PERM_VIEW, "none" => NC_PERM_NONE];
        foreach ($permcodes as $key => $val) {
            if ($this->_params['permissions'] == $val) {
                $emaildata['PERMISSIONS'] = $key;
            }
        }

        // send the email to all curators and to the user affected       
        $ncemail->sendEmailToCurators("email-update-permissions", $emaildata, $netid, [$emaildata['TARGETID']]);
        
    }

}

?>
