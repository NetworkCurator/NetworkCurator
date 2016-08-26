<?php

/*
 * Post-installation script that populates the database with dummy data.
 * (For debugging and testing only)
 * 
 */

sleep(1);

echo "\n";
echo "NetworkCurator - test data\n\n";
include_once "../config/nc-config.php";
include_once "../../nc-api/helpers/nc-db.php";
include_once "../../nc-api/helpers/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";


// remove networks that may be stored under the networks directory
$netdir = "/var/www/NetworkCurator/nc-data/networks/";
array_map('rmdir', glob($netdir."*"));


/* --------------------------------------------------------------------------
 * Prep - get admin user information
 * -------------------------------------------------------------------------- */

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
 * Create dummy users in the database
 * -------------------------------------------------------------------------- */

try {
    $newusers = array('Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo');
    foreach ($newusers as $nu) {
        echo "Creating user $nu\n";
        $params = array('controller' => 'NCUsers', 'action' => 'createNewUser',
            'user_id' => 'admin', 'user_extpwd' => $upw,
            'target_extpwd' => $upw, 'user_ip' => 'install-testdata',
            'target_firstname' => $nu, 'target_middlename' => '', 'target_lastname' => 'Test'.$nu,
            'target_email' => 'test@test.com',
            'target_id' => strtolower($nu), 'target_password' => strtolower($nu) . '123');
        $ok = $NCapi->sendRequest($params);
    }
} catch (Exception $e) {
    echo "Exception while creating users: " . $e->getMessage();
}


try {
    $newnetworks = array('net-zulu', 'test-yankee', 'xray');
    foreach ($newnetworks as $nn) {
        echo "Creating network $nn\n";
        $params = array('controller' => 'NCNetworks', 'action' => 'createNewNetwork',
            'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
            'network_name' => $nn, 'network_title' => 'ABC ' . $nn . ' title',
            'network_desc' => 'XYZ ' . $nn . ' description');       
        $ok = $NCapi->sendRequest($params);
    }
} catch (Exception $e) {
    echo "Exception while creating networks: " . $e->getMessage();
}


echo "\n";
?>
