<?php

/*
 * Post-installation script that populates the database with dummy data.
 * (For debugging and testing only)
 * 
 */

sleep(3);

echo "\n";
echo "NetworkCurator - test data\n\n";
include_once "../config/nc-config.php";
include_once "../../nc-core/php/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";


/* --------------------------------------------------------------------------
 * Prep - get admin user information
 * -------------------------------------------------------------------------- */

// Create connection to database
$conn1 = mysqli_connect(NC_DB_SERVER, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD, NC_DB_NAME);
if (!$conn1) {
    die("Connection failed: " . mysqli_error($conn1) . "\n");
}

// get information about the admin user
$sql = "SELECT user_id, user_pwd, user_extpwd FROM " . NC_TABLE_USERS . "
          WHERE BINARY user_id='admin'";
$sqlresult = mysqli_query($conn1, $sql);
if (!$sqlresult) {
    echo mysqli_error($conn1) . "\n\n";
}
$row = mysqli_fetch_array($sqlresult);
$uid = 'admin';
$upw = $row['user_extpwd'];


// set up the API and a core array of parameters
// echo "using ".NC_APP_ID." and ".NC_APP_KEY." ".NC_API_PATH."\n";
// echo "\n\n";
$NCapi = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);
$coreparams = array('user_id' => 'admin', 'user_extpwd' => $upw);



/* --------------------------------------------------------------------------
 * Create dummy users in the database
 * -------------------------------------------------------------------------- */

try {
    $newusers = array('Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo');
    foreach ($newusers as $nu) {
        echo "Creating user $nu\n";
        $params = array('controller' => 'NCUsers', 'action' => 'createNewUser',
            'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
            'firstname' => $nu, 'middlename' => '', 'lastname' => 'Test',
            'email' => 'test@test.com',
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
            'network_name' => $nn, 'network_title'=>'ABC '.$nn.' title', 
            'network_desc'=>'XYZ '.$nn.' description');
        $ok = $NCapi->sendRequest($params);
    }
} catch (Exception $e) {
    echo "Exception while creating networks: " . $e->getMessage();
}


echo "\n";
?>
