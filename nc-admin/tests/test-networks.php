<?php

/*
 * Post-installation script that test some functions in NCNetworks.
 * (For debugging and testing only)
 * 
 */

echo "\n";
echo "NetworkCurator - NCNetworks \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Create some classes for the zulu network
 * -------------------------------------------------------------------------- */

$params = array('controller' => 'NCNetworks', 'action' => 'listNetworks',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata');
$result = tryreport($NCapi, $params, true);
print_r($result);


$params = array('controller' => 'NCNetworks', 'action' => 'getNetworkMetadata',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata', 
    'network'=>'xray');
$result = tryreport($NCapi, $params, true);
//print_r($result);


?>
