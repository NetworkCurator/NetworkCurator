<?php
/**
 * Collection of functions dealing with NetworkCurator database management
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */


/**
 * Create a connection to the NC database
 * 
 */
function connectNetworkCuratorDB() {
    $conn = mysqli_connect(NC_SERVER, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD, NC_DB_NAME);
    if (!$conn) {
        die("Connection failed: " . mysqli_error($conn) . "<br/>");
    }
    return $conn;
}

?>
