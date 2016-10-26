<?php

/*
 * Command line tool.
 * Purges all data related to a network from the local database 
 * 
 */

// print a usage message if called without any arguments
if (count($argv)<2) {
    echo "USAGE: php purge-network NETWORKNAME\n";
    exit();
}

// extract target network name from first argument (after purge-network.php)
$network = $argv[1];

echo "NetworkCurator - purging network $network: ";

/* --------------------------------------------------------------------------
 * Prep - get admin user information
 * -------------------------------------------------------------------------- */

include_once "../config/nc-config.php";
include_once "../../nc-api/helpers/nc-db.php";
include_once "../../nc-api/helpers/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";

$db = connectDB(NC_DB_SERVER, NC_DB_NAME, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD);

// get information about the admin user
$sql = "SELECT user_id, user_pwd, user_extpwd FROM " . NC_TABLE_USERS . "
          WHERE BINARY user_id='admin'";
$stmt = $db->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$uid = 'admin';
$upw = $row['user_extpwd'];

// set up the API and a core array of parameters
// echo "using ".NC_APP_ID." and ".NC_APP_KEY." ".NC_API_PATH."\n";
// echo "\n\n";
$NCapi = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);


/* --------------------------------------------------------------------------
 * Send command to purge a network
 * -------------------------------------------------------------------------- */

$params = array('controller' => 'NCNetworks', 'action' => 'purgeNetwork',
    'user_id' => 'admin', 'user_extpwd' => $upw,
    'target_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => $network);

$result = null;
try {
    $result = $NCapi->sendRequest($params);
    echo "\t".$result;
} catch (Exception $ex) {
    echo "\tErr: " . $ex->getMessage() . "\n";
}

echo "\n";
?>
