<?php

/**
 * Class handling requests for networks (list networks, add a new network, etc.)
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 * 
 */
class NCNetworks extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger    
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_netid;

    /**
     * Constructor 
     * 
     * @param type $db
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
        if (isset($params['network'])) {
            $this->_network = $params['network'];
            $this->_netid = $this->getNetworkId($params['network']);
        } else {
            $this->_network = "";
            $this->_netid = "";
        }
        if (isset($params['user_id'])) {
            $this->_uid = $params['user_id'];
        } else {
            throw new Exception("NCNetworks requires parameter user_id");
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

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, array_keys($this->_annotypes));

        if (!$this->validateNameString($params['name'])) {
            throw new Exception("Invalid network name");
        }

        // perform tests on whether this user can create new network?
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to create a network");
        }

        $this->dblock([NC_TABLE_NETWORKS, NC_TABLE_ANNOTEXT, NC_TABLE_ACTIVITY, NC_TABLE_PERMISSIONS]);

        // check that the network does not already exist? 
        if ($this->getNetworkId($params['name']) != "") {
            throw new Exception("Network name exists");
        }

        // if reached here, create the new network
        // find a new ids for the network and annotations                 
        $netid = $this->makeRandomID(NC_TABLE_NETWORKS, 'network_id', NC_PREFIX_NETWORK, NC_ID_LEN);

        // create a directory on the server for the network        
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $netid;
        if (!mkdir($networkdir, 0777, true)) {
            throw new Exception("Failed creating network data space: " . $networkdir);
        }
        chmod($networkdir, 0777);

        // insert a new row into the networks table and annotations       
        $sql = "INSERT INTO " . NC_TABLE_NETWORKS . " (network_id, owner_id) VALUES (?, ?)";
        $this->qPE($sql, [$netid, $this->_uid]);

        // create a starting log entry for creation of the network                
        $this->logActivity($this->_uid, $netid, "created network", $params['name'], $params['title']);
        $this->logAction($this->_uid, $this->_params['source_ip'], "NCNetworks", "createNewNetwork", $netid . ": " . $params['name']);

        // create permissions for admin and guest
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . "
                   (user_id, network_id, permissions) VALUES (?, ?, ?)";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(['admin', $netid, NC_PERM_SUPER]);
        $stmt->execute(['guest', $netid, NC_PERM_NONE]);

        // create starting annotations for the title, abstract, contents
        // insert annotation for network name   
        $this->batchInsertAnnoSets($netid, [$params], [$netid]);

        $this->dbunlock();

        // send a welcome email
        $this->sendNewNetworkEmail();

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
        $guestperm = (int) $this->getUserPermissions($this->_netid, "guest");
        return $guestperm > NC_PERM_NONE;
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
        $tp = "" . NC_TABLE_PERMISSIONS;
        $ta = "" . NC_TABLE_ANNOTEXT;
        $tac = $ta . ".anno_type";
        $tat = $ta . ".anno_text";
        $tai = $ta . ".anno_id";
        $ni = "network_id";

        $sql = "
SELECT $ni AS id,
    GROUP_CONCAT(name SEPARATOR '') AS name,         
    GROUP_CONCAT(title SEPARATOR '') AS title,    
    GROUP_CONCAT(title_id SEPARATOR '') AS title_id, 
    GROUP_CONCAT(abstract SEPARATOR '') AS abstract,
    GROUP_CONCAT(abstract_id SEPARATOR '') AS abstract_id        
FROM (SELECT $tp.$ni AS $ni,
    (CASE WHEN $tac = " . NC_NAME . " THEN $tat ELSE '' END) AS 'name',
    (CASE WHEN $tac = " . NC_TITLE . " THEN $tat ELSE '' END) AS 'title',
    (CASE WHEN $tac = " . NC_TITLE . " THEN $tai ELSE '' END) AS 'title_id',
    (CASE WHEN $tac = " . NC_ABSTRACT . " THEN $tat ELSE '' END) AS 'abstract',
    (CASE WHEN $tac = " . NC_ABSTRACT . " THEN $tai ELSE '' END) AS 'abstract_id'    
FROM $ta JOIN $tp ON $ta.$ni = $tp.$ni
    WHERE BINARY $tp.user_id = '$uid' AND $tp.permissions>" . NC_PERM_NONE . "
    AND $ta.root_id LIKE 'W%'
    AND $ta.anno_status = 1 AND $tac <=" . NC_ABSTRACT . "
GROUP BY $ta.network_id, $tac) AS T GROUP BY network_id ORDER BY title";

        $stmt = $this->_db->query($sql);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Get information about all users allowed to interact with a network
     * 
     * @return type
     * @throws Exception
     */
    public function listNetworkUsers() {

        $tu = "" . NC_TABLE_USERS;
        $tp = "" . NC_TABLE_PERMISSIONS;

        // check that requesting user can view this network       
        $uperm = $this->getUserPermissions($this->_netid, $this->_uid);
        if ($uperm < NC_PERM_VIEW) {
            throw new Exception("Insufficient permission to view network");
        }

        // get the users participating in this network       
        $sql = "SELECT user_firstname, user_middlename, user_lastname,
               $tu.user_id AS user_id, permissions
                  FROM $tp JOIN $tu ON $tp.user_id = $tu.user_id
                  WHERE BINARY $tp.user_id!='admin' AND $tp.user_id!='guest'
                     AND $tp.network_id = ? AND $tp.permissions>" . NC_PERM_NONE . "
                     ORDER BY $tu.user_id";
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = array();
        while ($row = $stmt->fetch()) {
            $result[$row['user_id']] = $row;
        }
        return $result;
    }

    /**
     * For a given network, fetches title, description and arrays
     * with curators, authors, and commentators
     * 
     * @return array
     * @throws Exception
     */
    public function getNetworkMetadata() {

        // check if user has permission to view the table        
        if ($this->getUserPermissions($this->_netid, $this->_uid) < NC_PERM_VIEW) {
            throw new Exception("Insufficient permission to view the network");
        }

        // get a full summary (name, title, abstract, content)
        $result = $this->getFullSummaryFromRootId($this->_netid, $this->_netid, true);
        $result['network_id'] = $this->_netid;

        // find the users who have privileges on the network
        $tu = "" . NC_TABLE_USERS;
        $tp = "" . NC_TABLE_PERMISSIONS;
        $sql = "SELECT user_firstname, user_middlename, user_lastname,
                $tp.user_id, permissions
                FROM $tp JOIN $tu ON $tp.user_id = $tu.user_id
                WHERE $tp.network_id = ? AND $tp.permissions>" . NC_PERM_VIEW . "
                    AND $tp.permissions<=" . NC_PERM_CURATE . "
                ORDER BY $tu.user_lastname, $tu.user_firstname, $tu.user_middlename";
        $stmt = $this->qPE($sql, [$this->_netid]);
        // move information into three new arrays by permission level
        $curators = array();
        $authors = array();
        $commentators = array();
        while ($row = $stmt->fetch()) {
            if ($row['permissions'] == NC_PERM_CURATE) {
                $curators[] = $row;
            } else if ($row['permissions'] == NC_PERM_EDIT) {
                $authors[] = $row;
            } else if ($row['permissions'] == NC_PERM_COMMENT) {
                $commentators[] = $row;
            }
        }
        // attach the new arrays to the answer object
        $result['curators'] = $curators;
        $result['authors'] = $authors;
        $result['commentators'] = $commentators;

        return $result;
    }

    /**
     * Fetch title for a given network. This is a short verion of getNetworkMetadata
     * 
     * @return array
     * @throws Exception
     */
    public function getNetworkTitle() {

        // check if user has permission to view the table        
        if ($this->getUserPermissions($this->_netid, $this->_uid) < NC_PERM_VIEW) {
            throw new Exception("Insufficient permission to view the network");
        }

        $ta = "" . NC_TABLE_ANNOTEXT;
        // find the title, abstract, etc
        $sql = "SELECT anno_text FROM $ta 
              WHERE BINARY network_id = ? AND root_id = ?
                AND anno_status = " . NC_ACTIVE . " AND anno_type = " . NC_TITLE;
        $stmt = $this->qPE($sql, [$this->_netid, $this->_netid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching network title");
        }
        return $result['anno_text'];
    }

    /**
     * get an array with a summary of activity relevant to a network
     * 
     * @return array
     * 
     * @throws Exception
     */
    public function getNetworkActivity() {

        // settings for limiting output
        $offset = 0;
        $limit = 50;
        $startdate = null;
        $enddate = null;

        // get values for limiting output from params
        if (isset($this->_params['offset'])) {
            $offset = abs((int) ($this->_params['offset']));
        }
        if (isset($this->_params['limit'])) {
            $limit = abs((int) ($this->_params['limit']));
        }
        if (isset($this->_params['startdate'])) {
            $startdate = $this->_params['startdate'];
        }
        if (isset($this->_params['enddate'])) {
            $enddate = $this->_params['enddate'];
        }

        // some checks on date-based filtering
        if ((!is_null($startdate) && is_null($enddate)) ||
                (is_null($startdate) && !is_null($enddate))) {
            throw new Exception("Invalid date interval");
        }

        // query the activity log table
        $sql = "SELECT datetime, user_id, action, target_name, value FROM " .
                NC_TABLE_ACTIVITY . " WHERE network_id = ? ";
        $sqlorder = "ORDER BY datetime DESC ";
        if (is_null($startdate)) {
            $sql .= $sqlorder . " LIMIT $limit OFFSET $offset";
            $stmt = $this->qPE($sql, [$this->_netid]);
        } else {
            $sql .= " WHERE datetime >= ? AND datetime <= ? $sqlorder";
            $stmt = $this->qPE($sql, [$this->_netid, $startdate, $enddate]);
        }

        $result = array();
        while ($row = $stmt->fetch()) {
            if (strlen($row['value']) > 128) {
                $row['value'] = substr($row['value'], 0, 125) . "...";
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * 
     * @return int
     * 
     * total number of entries in the network activity log
     * 
     */
    public function getActivityLogSize() {

        $sql = "SELECT COUNT(*) AS logsize FROM " .
                NC_TABLE_ACTIVITY . " WHERE network_id = ? ";
        $stmt = $this->qPE($sql, [$this->_netid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching error log");
        } else {
            return $result['logsize'];
        }
    }

    /**
     * Remove all data related to a network.
     * Can be invoked only by admin user (mainly for developer use)
     * 
     * @return string
     * 
     * message with purge summary
     * 
     */
    public function purgeNetwork() {

        // this action only for admin
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to purge the network");
        }

        if ($this->_network == "") {
            throw new Exception("Missing network name");
        }
        if ($this->_netid == "") {
            throw new Exception("Network does not exist");
        }

        // proceed to purge the network
        // send email first - requires access to users associated with the network
        $this->sendPurgeNetworkEmail();

        // remove all entries from db tables
        $alltables = [NC_TABLE_PERMISSIONS, NC_TABLE_ANNOTEXT,
            NC_TABLE_CLASSES, NC_TABLE_FILES, NC_TABLE_ACTIVITY, NC_TABLE_NETWORKS, NC_TABLE_NODES,
            NC_TABLE_PERMISSIONS, NC_TABLE_POSITIONS];
        foreach ($alltables as $dbtable) {
            $sql = "DELETE FROM $dbtable WHERE network_id = ?";
            $this->qPE($sql, [$this->_netid]);
        }

        // remove data directory for the network
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $this->_netid;
        $result = "Removed network " . $this->_network . " [" . $this->_netid . "] \n";
        system("rm -fr $networkdir");

        // record the action in the site log
        $this->logAction($this->_uid, $this->_params['source_ip'], "NCNetworks", "purgeNetwork", $this->_netid . ": " . $this->_network);

        return $result;
    }

    /**
     * Send an email about a new network
     */
    private function sendNewNetworkEmail() {
        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK' => $this->_params['name']];
        $ncemail->sendEmailToUsers("email-new-network", $emaildata, ['admin']);
    }

    /**
     * Send an email about a network purge
     */
    private function sendPurgeNetworkEmail() {
        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK' => $this->_network, 'USER' => $this->_uid];
        $ncemail->sendEmailToNetwork("email-purge-network", $emaildata, $this->_netid, ['admin']);
    }

}

?>
