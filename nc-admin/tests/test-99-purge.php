<?php

/*
 * Post-installation script that tests API controllers: NCNetworks and NCUsers
 * (Cleans up networks created during previous tests)
 * 
 */

echo "\n";
echo "test-99-purge: testing database cleanup (puring networks and users) \n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Purget test networks
 * -------------------------------------------------------------------------- */

$purgenetworks = ["nc-test-xray" => true, "nc-test-yankee" => true, "nc-test-zulu" => true,
    "nc-test-nonexistent" => false];
foreach (array_keys($purgenetworks) as $nn) {
    echo "Purging network $nn: ";
    $params = array('controller' => 'NCNetworks', 'action' => 'purgeNetwork',
        'user_id' => 'admin', 'user_extpwd' => $upw,
        'target_    extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => $nn);
    $result = tryreport($NCapi, $params, $purgenetworks[$nn]);
}


/* --------------------------------------------------------------------------
 * Purget test users
 * -------------------------------------------------------------------------- */

$purgeusers = ["alpha" => true, "bravo" => true, "charlie" => true,
    "delta" => true, "echo" => true,
    "bad user" => false];
foreach (array_keys($purgeusers) as $nu) {
    echo "Purging user $nu: ";
    $params = array('controller' => 'NCUsers', 'action' => 'purgeUser',
        'user_id' => 'admin', 'user_extpwd' => $upw,
        'target_    extpwd' => $upw, 'user_ip' => 'install-testdata',
        'target' => $nu);
    $result = tryreport($NCapi, $params, $purgeusers[$nu]);
}

?>
