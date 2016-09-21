<?php

/*
 * Tests for creating/adjusting user permissions
 * 
 */

echo "\n";
echo "NetworkCurator - some tests for NCUsers \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Adjust user permissions for net-zulu network
 * -------------------------------------------------------------------------- */

$newusers = array('alpha', 'bravo', 'charlie', 'admin', 'charlie', 'echo');
for ($i = 0; $i < count($newusers); $i++) {

    $uname = $newusers[$i];
    $uperm = 4 - $i;

    echo "Adjusting permissions for user $uname to $uperm";
    $params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_name' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'target_name' => $uname, 'permissions' => $uperm);
    tryreport($NCapi, $params);
}

echo "\n";
?>