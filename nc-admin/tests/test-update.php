<?php

/*
 * Tests for updating nodes and status in existing networks 
 * 
 */


echo "\n";
echo "NetworkCurator - some tests for NCData \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Import small dataset into net-zuli
 * -------------------------------------------------------------------------- */

$filenames = ["D3_2.json"];
$networks = ["net-status"];


for ($i = 0; $i < count($filenames); $i++) {

    $nowfile = $filenames[$i];
    $nownetwork = $networks[$i];
    
    echo "Updating from file $nowfile\n";
    $nowdata = json_encode(json_decode(file_get_contents($nowfile)));
    echo substr($nowdata, 0, 200) . " ...\n";

    $params = array('controller' => 'NCData', 'action' => 'importData',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => $nownetwork, 'file_name' => $nowfile,
        'data' => $nowdata, 'file_desc' => 'just testing');
    $result = tryreport($NCapi, $params, true);    
    echo $result."\n";
    
    echo "done\n\n\n";
}



?>
