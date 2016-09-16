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
        $params = $this->subsetArray($this->_params, ["user_id",
            "network_name", "network_title", "network_desc"]);

        // shorthand variables        
        $network = $this->_network;

        // perform tests on whether this user can create new network?
        if ($this->_uid !== "admin") {
            throw new Exception("Insufficient permissions to create a network");
        }

        // check that the network does not already exist? 
        if ($this->getNetworkId($network) !== "") {
            throw new Exception("Network name exists");
        }

        // if reached here, create the new network in 6 steps
        // 1/6, find a new ids for the network and annotations                 
        $netid = $this->makeRandomID(NC_TABLE_NETWORKS, 'network_id', 'W', NC_ID_LEN);

        // 2/6, create a directory on the server for the network        
        $networkdir = $_SERVER['DOCUMENT_ROOT'] . NC_DATA_PATH . "/networks/" . $netid;
        if (!mkdir($networkdir, 0777, true)) {
            throw new Exception("Failed creating network data space: " . $networkdir);
        }

        // 3/6, insert a new row into the networks table and annotations       
        $sql = "INSERT INTO " . NC_TABLE_NETWORKS . "
                   (network_id, owner_id) VALUES (?, ?)";
        try {
            $stmt = prepexec($this->_db, $sql, [$netid, $this->_uid]);
        } catch (Exception $ex) {
            throw new Exception("Error inserting new table");
        }

        // 4/6, create a starting log entry for creation of the network        
        $this->logActivity($this->_uid, $netid, "created network", $params['network_name'], $params['network_title']);

        // 5/6, create starting annotations for the title, abstract, contents
        // insert annotation for network name   
        $this->insertNewAnnoSet($netid, $this->_uid, $netid, $params['network_name'], $params['network_title'], $params['network_desc']);

        // 6/6, create permissions for admin and guest
        $sql = "INSERT INTO " . NC_TABLE_PERMISSIONS . "
                   (user_id, network_id, permissions) VALUES (?, ?, ?)";
        try {
            $stmt = $this->_db->prepare($sql);
            $stmt->execute(['admin', $netid, NC_PERM_SUPER]);
            $stmt->execute(['guest', $netid, NC_PERM_NONE]);
        } catch (Exception $ex) {
            throw new Exception("Error setting user permissions");
        }

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
        $netid = $this->getNetworkId($this->_network);
        $guestperm = (int) $this->getUserPermissionsNetID($netid, "guest");
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
        $tac = $ta . ".anno_level";
        $tat = $ta . ".anno_text";
        $tai = $ta . ".anno_id";
        $ni = "network_id";

        $sql = "
SELECT $ni,
    GROUP_CONCAT(name SEPARATOR '') AS network_name,         
    GROUP_CONCAT(title SEPARATOR '') AS network_title,    
    GROUP_CONCAT(abstract SEPARATOR '') AS network_abstract,
    GROUP_CONCAT(abstract_id SEPARATOR '') AS network_abstract_id        
FROM (SELECT $tp.$ni AS $ni,
    (CASE WHEN $tac = " . NC_NAME . " THEN $tat ELSE '' END) AS 'name',
    (CASE WHEN $tac = " . NC_TITLE . " THEN $tat ELSE '' END) AS 'title',
    (CASE WHEN $tac = " . NC_ABSTRACT . " THEN $tat ELSE '' END) AS 'abstract',
    (CASE WHEN $tac = " . NC_ABSTRACT . " THEN $tai ELSE '' END) AS 'abstract_id'    
FROM $ta JOIN $tp ON $ta.$ni = $tp.$ni
    WHERE BINARY $tp.user_id = '$uid' AND $tp.permissions>" . NC_PERM_NONE . "
    AND $ta.anno_status = 1 AND $tac <=" . NC_ABSTRACT . "
GROUP BY $ta.network_id, $tac) AS T GROUP BY network_id";
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

        $netid = $this->getNetworkId($this->_network);
        // check that requesting user can view this network       
        $uperm = $this->getUserPermissionsNetID($netid, $this->_uid);
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
        $stmt = prepexec($this->_db, $sql, [$netid]);
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

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network, true);
        
        // check if user has permission to view the table        
        if ($this->getUserPermissionsNetID($netid, $this->_uid) < NC_PERM_VIEW) {
            throw new Exception("Insufficient permission to view the network");
        }

        $tu = "" . NC_TABLE_USERS;
        $ta = "" . NC_TABLE_ANNOTEXT;
        $tp = "" . NC_TABLE_PERMISSIONS;

        // find the title, abstract, etc
        $sql = "SELECT network_id, anno_id, anno_level, anno_text FROM $ta 
              WHERE BINARY network_id = ? AND root_id = ?
                AND anno_status = " . NC_ACTIVE . " AND anno_level <= " . NC_CONTENT;
        $stmt = prepexec($this->_db, $sql, [$netid, $netid]);

        // record the results into an array that will eventually be output
        $result = array('network_id' => $netid);
        while ($row = $stmt->fetch()) {
            switch ($row['anno_level']) {
                case NC_NAME:
                    $result['network_name'] = $row['anno_text'];
                    $result['network_name_id'] = $row['anno_id'];
                    break;
                case NC_TITLE:
                    $result['network_title'] = $row['anno_text'];
                    $result['network_title_id'] = $row['anno_id'];
                    break;
                case NC_ABSTRACT:
                    $result['network_abstract'] = $row['anno_text'];
                    $result['network_abstract_id'] = $row['anno_id'];
                    break;
                case NC_CONTENT:
                    $result['network_content'] = $row['anno_text'];
                    $result['network_content_id'] = $row['anno_id'];
                    break;
                default:
                    break;
            }
        }

        // find the users who are curators on the network
        $sql = "SELECT user_firstname, user_middlename, user_lastname,
                $tp.user_id, permissions
                FROM $tp JOIN $tu ON $tp.user_id = $tu.user_id
                WHERE $tp.network_id = ? AND $tp.permissions>" . NC_PERM_VIEW . "
                    AND $tp.permissions<=" . NC_PERM_CURATE . "
                ORDER BY $tu.user_lastname, $tu.user_firstname, $tu.user_middlename";
        $stmt = prepexec($this->_db, $sql, [$netid]);

        // move information from sql result into three new arrays by permission level
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

        // find the network id that corresponds to the name
        $netid = $this->getNetworkId($this->_network, true);        

        // check if user has permission to view the table        
        if ($this->getUserPermissionsNetID($netid, $this->_uid) < NC_PERM_VIEW) {
            throw new Exception("Insufficient permission to view the network");
        }

        $ta = "" . NC_TABLE_ANNOTEXT;
        // find the title, abstract, etc
        $sql = "SELECT anno_text FROM $ta 
              WHERE BINARY network_id = ? AND root_id = ?
                AND anno_status = " . NC_ACTIVE . " AND anno_level = " . NC_TITLE;
        $stmt = prepexec($this->_db, $sql, [$netid, $netid]);
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

        $netid = $this->getNetworkId($this->_network, true);        

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
            $stmt = prepexec($this->_db, $sql, [$netid]);
        } else {
            $sql .= " WHERE datetime >= ? AND datetime <= ? $sqlorder";
            $stmt = prepexec($this->_db, $sql, [$netid, $startdate, $enddate]);
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
        $netid = $this->getNetworkId($this->_network, true);

        $sql = "SELECT COUNT(*) AS logsize FROM " .
                NC_TABLE_ACTIVITY . " WHERE network_id = ? ";
        $stmt = prepexec($this->_db, $sql, [$netid]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching error log");
        } else {
            return $result['logsize'];
        }
    }

}

?>
