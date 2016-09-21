<?php

/*
 * Tests for creating/editing nodes and links classes 
 * 
 */


echo "\n";
echo "NetworkCurator - some tests for NCData \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Import small dataset into net-zuli
 * -------------------------------------------------------------------------- */

$filenames = ["A1.json", "B1.json"];
$networks = ["net-yankee", "net-zulu"];


for ($i = 0; $i < count($filenames); $i++) {

    $nowfile = $filenames[$i];
    $nownetwork = $networks[$i];

    echo "Importing from file $nowfile\n";
    $nowdata = json_encode(json_decode(file_get_contents($nowfile)));
    echo substr($nowdata, 0, 200) . " ...\n";

    $params = array('controller' => 'NCData', 'action' => 'importData',
        'user_name' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => $nownetwork, 'file_name' => $nowfile,
        'file_content' => $nowdata, 'file_desc' => 'just testing');
    tryreport($NCapi, $params);
    
    echo "done\n\n\n";
}



?>
