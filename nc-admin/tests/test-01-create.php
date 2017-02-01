<?php

/*
 * Post-installation script testing controller NCNetworks and NCUsers
 * (For debugging and testing only)
 * 
 */

sleep(1);

echo "\n";
echo "test-01-create: testing creating new users and new networks\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Create users
 * -------------------------------------------------------------------------- */

$newusers = array('Alpha' => true, 'Bravo' => true, 'Charlie' => true,
    'Delta' => true, 'Echo' => true,
    'Bad name' => false, // spaces in username
    'Another#bad' => false, // special characters in username    
);
foreach (array_keys($newusers) as $nu) {
    echo "Creating user $nu";
    $params = array('controller' => 'NCUsers', 'action' => 'createNewUser',
        'user_id' => 'admin', 'user_extpwd' => $upw,
        'target_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'target_firstname' => $nu, 'target_middlename' => '', 
        'target_lastname' => 'Test' . $nu,
        'target_email' => 'admin@'.NC_SITE_DOMAIN,
        'target_id' => strtolower($nu), 'target_password' => strtolower($nu) . '123');
    tryreport($NCapi, $params, $newusers[$nu]);
}

$newusers = array('Alpha' => false // duplicate name
);
foreach (array_keys($newusers) as $nu) {
    echo "Creating user $nu";
    $params = array('controller' => 'NCUsers', 'action' => 'createNewUser',
        'user_id' => 'admin', 'user_extpwd' => $upw,
        'target_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'target_firstname' => $nu, 'target_middlename' => '', 
        'target_lastname' => 'Test' . $nu,
        'target_email' => 'admin@'.NC_SITE_DOMAIN,
        'target_id' => strtolower($nu), 'target_password' => strtolower($nu) . '123');
    tryreport($NCapi, $params, $newusers[$nu]);
}


/* --------------------------------------------------------------------------
 * Create networks 
 * -------------------------------------------------------------------------- */

$newnetworks = array('nc-test-zulu' => true,
    'nc-test-yankee' => true,
    'nc-test-xray' => true,
    'A' => false  // short network name    
);
foreach (array_keys($newnetworks) as $nn) {
    echo "Creating network $nn";
    $params = array('controller' => 'NCNetworks', 'action' => 'createNewNetwork',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'name' => $nn, 'title' => 'ABC ' . $nn . ' title',
        'abstract' => 'XYZ ' . $nn . ' description', 'content' => '');
    tryreport($NCapi, $params, $newnetworks[$nn]);
}

$newnetworks = array('nc-test-zulu' => false // duplicate name     
);
foreach (array_keys($newnetworks) as $nn) {
    echo "Creating network $nn";
    $params = array('controller' => 'NCNetworks', 'action' => 'createNewNetwork',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'name' => $nn, 'title' => 'ABC ' . $nn . ' title',
        'abstract' => 'XYZ ' . $nn . ' description', 'content' => '');
    tryreport($NCapi, $params, $newnetworks[$nn]);
}

?>
