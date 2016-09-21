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
array_map('rmdir', glob($netdir . "*"));


/* --------------------------------------------------------------------------
 * Create dummy users in the database
 * -------------------------------------------------------------------------- */


$newusers = array('Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo');
foreach ($newusers as $nu) {
    echo "Creating user $nu";
    $params = array('controller' => 'NCUsers', 'action' => 'createNewUser',
        'user_name' => 'admin', 'user_extpwd' => $upw,
        'target_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'target_firstname' => $nu, 'target_middlename' => '', 'target_lastname' => 'Test' . $nu,
        'target_email' => 'test@test.com',
        'target_name' => strtolower($nu), 'target_password' => strtolower($nu) . '123');
    tryreport($NCapi, $params);
}



$newnetworks = array('net-zulu', 'net-yankee', 'xray');
foreach ($newnetworks as $nn) {
    echo "Creating network $nn";
    $params = array('controller' => 'NCNetworks', 'action' => 'createNewNetwork',
        'user_name' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => $nn, 'network_title' => 'ABC ' . $nn . ' title',
        'network_abstract' => 'XYZ ' . $nn . ' description');
    tryreport($NCapi, $params);
}


echo "\n";
?>
