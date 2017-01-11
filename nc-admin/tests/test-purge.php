<?php

/*
 * Tests for purging an existing network
 * 
 */


echo "\n";
echo "NetworkCurator - some tests for NCData \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Import small dataset into net-zuli
 * -------------------------------------------------------------------------- */

$networks = ["net-status"];


for ($i = 0; $i < count($networks); $i++) {

    $nownetwork = $networks[$i];

    echo "Puring network $nownetwork...";
    $params = array('controller' => 'NCNetworks', 'action' => 'purgeNetwork',
        'user_id' => 'admin', 'user_extpwd' => $upw,
        'target_    extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => $nownetwork);

    $result = tryreport($NCapi, $params, true);
    echo $result . "\n";

    echo "done\n\n\n";
}
?>
