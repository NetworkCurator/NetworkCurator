<?php

/*
 * Class handling logging activity into the _activity and _log tables.
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */

class NCLogger {

    private $_conn;

    /**
     * Constructor with connection to database
     * 
     * @param type $conn
     * 
     */
    public function __construct($conn) {
        $this->_conn = $conn;
    }

    /**
     * Add entry into log table
     * 
     * @param type $userid
     * @param type $userip
     * @param type $action
     * @param type $value
     * @throws Exception
     * 
     */
    public function addEntry($userid, $userip, $controller, $action, $value) {
        $sql = "INSERT INTO " . NC_TABLE_LOG . "
            (datetime, user_id, user_ip, controller, action, value) VALUES 
            (UTC_TIMESTAMP(), '$userid', '$userip', '$controller', '$action', '$value')";
        //echo $sql. "\n";
        $sqlresult = mysqli_query($this->_conn, $sql);
        //echo "done\n";
        if (!$sqlresult) {
            throw new Exception("Error recording log entry: $action $value");
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
    public function logActivity($uid, $netid, $msg, $targetid, $value) {
        $sql = "INSERT INTO " . NC_TABLE_ACTIVITY . "
                   (datetime, user_id, network_id, action, target_id, value) 
                   VALUES 
                   (UTC_TIMESTAMP(), '$uid', '$netid', '$msg', 
                       '$targetid', '$value')";
        if (!mysqli_query($this->_conn, $sql)) {
            throw new Exception("Failed logging activity: " . mysqli_error($conn));
        }
        return 1;
    }

}

?>
