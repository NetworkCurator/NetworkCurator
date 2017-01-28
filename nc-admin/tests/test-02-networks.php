<?php

/*
 * Post-installation script that tests API controller: NCNetworks.php
 * (For debugging and testing only)
 * Note that these tests report "ok" if the API calls do not throw an error
 * To make sure that calls actually return correct data, uncomment the print_r 
 * statements and look through the info manually
 * 
 */

echo "\n";
echo "test-02-networks: testing network metadata \n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Fetch existing networks and metadata
 * -------------------------------------------------------------------------- */

echo "Fetching list of all networks: ";
$params = array('controller' => 'NCNetworks', 'action' => 'listNetworks',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata');
$result = tryreport($NCapi, $params, true, true);
//print_r($result);


echo "Fetching network metadata for nc-test-xray: ";
$params = array('controller' => 'NCNetworks', 'action' => 'getNetworkMetadata',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata', 
    'network'=>'nc-test-xray');
$result = tryreport($NCapi, $params, true, true);
//print_r($result);


?>
