<?php

/*
 * Post-installation script that tests API controller: NCData
 * (For debugging and testing only)
 * 
 * Tests report "ok" when the API call does not throw exception.
 * To see what changes are made through the data imports, check the API call outputs
 * manually and check modifications to the network through the web GUI. 
 * 
 */

echo "\n";
echo "test-11-update: testing updating networks through data files\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Import small dataset into net-zuli
 * -------------------------------------------------------------------------- */

$filenames = ["nc-test-xray-4.json"];
$networks = ["nc-test-xray"];


for ($i = 0; $i < count($filenames); $i++) {

    $nowfile = $filenames[$i];
    $nownetwork = $networks[$i];
    
    echo "Updating from file $nowfile: ";
    $nowdata = json_encode(json_decode(file_get_contents($nowfile)));    
    $params = array('controller' => 'NCData', 'action' => 'importData',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => $nownetwork, 'file_name' => $nowfile,
        'data' => $nowdata, 'file_desc' => 'just testing');
    $result = tryreport($NCapi, $params, true, true);    
    //echo $result."\n\n";
        
}



?>
