<?php

/*
 * Post-installation script that tests API controller: NCUsers
 * (For debugging and testing only) 
 * 
 */

echo "\n";
echo "test-03-create: testing adjusting user permissions\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Adjust user permissions 
 * -------------------------------------------------------------------------- */

$users = array('alpha'=>4, 'bravo'=>3, 'charlie'=>2, 'charlie'=>1);
foreach ($users as $uid=>$newperm) {    
    echo "Adjusting permissions for $uid on nc-test-zulu to $newperm: ";
    $params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu', 'target_id' => $uid, 'permissions' => $newperm);
    tryreport($NCapi, $params, true);
    
}

echo "Adjusting permissions for bravo on nc-test-xray: "; 
$params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-xray', 'target_id' => 'bravo', 'permissions' => 4);
tryreport($NCapi, $params, true);



// Negative examples

echo "Removing permissions for admin on nc-test-xray: "; 
$params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-xray', 'target_id' => 'admin', 'permissions' => 0);
tryreport($NCapi, $params, false);


echo "Removing permissions for echo on nc-test-xray: "; 
$params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-xray', 'target_id' => 'echo', 'permissions' => 0);
tryreport($NCapi, $params, false);


echo "Adjusting permissions for bravo on nc-test-nonexistent: "; 
$params = array('controller' => 'NCUsers', 'action' => 'updatePermissions',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-update', 'target_id' => 'bravo', 'permissions' => 4);
tryreport($NCapi, $params, false);

?>