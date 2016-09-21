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
tryreport($NCapi, $params);




?>
