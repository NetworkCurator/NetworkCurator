<?php

/*
 * Post-installation script that populates the database with dummy data.
 * (For debugging and testing only)
 * 
 */

sleep(2);

echo "\n";
echo "NetworkCurator - test data\n\n";
include_once "test-prep.php";

// remove networks that may be stored under the networks directory
$netdir = "/var/www/NetworkCurator/nc-data/networks/";
umask(0777);
system("rm -f /var/www/NetworkCurator/nc-data/networks/*/*");
array_map('rmdir', glob($netdir."*"));


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
    echo "\n";
}


try {
    $newnetworks = array('net-zulu', 'net-yankee', 'xray');
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
    echo "\n";
}


echo "\n";
?>
