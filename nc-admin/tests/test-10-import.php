<?php

/* 
 * Post-installation script that tests API controller: NCData
 * (For debugging and testing only)
 * 
 */

echo "\n";
echo "test-10-import: testing network import from files\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Import small dataset into net-zuli
 * -------------------------------------------------------------------------- */

$filenames = ["nc-test-yankee-1.json", "nc-test-yankee-1.json",
    "nc-test-xray-1.json", "nc-test-xray-2.json", 
    "nc-test-xray-3.json"];
$networks = ["nc-test-yankee", "nc-test-zulu", 
    "nc-test-xray", "nc-test-xray", 
    "nc-test-xray"];
$expected = [true, false, // false because file and command will mismatch networks
        true, false, // misspecified block inside file
        true];

for ($i = 0; $i < count($filenames); $i++) {

    $nowfile = $filenames[$i];
    $nownetwork = $networks[$i];
   
    echo "Importing from file $nowfile: ";
    $nowdata = json_encode(json_decode(file_get_contents($nowfile)));    
    $params = array('controller' => 'NCData', 'action' => 'importData',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => $nownetwork, 'file_name' => $nowfile,
        'data' => $nowdata, 'file_desc' => 'just testing');
    $result = tryreport($NCapi, $params, $expected[$i], true);    
    //echo $result."\n\n";
        
}



?>
