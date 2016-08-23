<?php

/*
 * Provides validation for a user/network combination
 * 
 * Class assumes that the NC configuration definitions are already loaded
 * 
 */

class NCAccess {

    // connection to db
    private $_conn;
    
    /**
     * Constructor
     */
    public function __construct($conn) {
        $this->_conn = $conn;
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
    public function getUserPermissions($network, $uid) {

        $tn = "" . NC_TABLE_NETWORKS;
        $tp = "" . NC_TABLE_PERMISSIONS;

        $sql = "SELECT permissions            
            FROM $tp JOIN $tn ON $tp.network_id=$tn.network_id                
            WHERE BINARY $tp.user_id='$uid' AND $tn.network_name='$network'";        
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