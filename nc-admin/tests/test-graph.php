<?php

/*
 * Tests for creating/editing nodes and links classes 
 * 
 */


echo "\n";
echo "NetworkCurator - some tests for NCGraphs \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Create some nodes and links for the zulu network
 * -------------------------------------------------------------------------- */

$nodenames = array('NA1', 'NA2', 'NA3', 'NA4', 'NA1', 'NA0');
$nodeclasses = array('NODE_A', 'NODE_A', 'NODE_B', 'NODE_A', 'NODE_B', 'NODE_BAD');
for ($i = 0; $i < count($nodenames); $i++) {
    $nowname = $nodenames[$i];
    $nowclass = $nodeclasses[$i];
    echo "Creating node $nowname ($nowclass)";
    $params = array('controller' => 'NCGraphs', 'action' => 'createNewNode',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'node_name' => $nowname, 'node_title' => "Node $nowname",
        'class_name' => $nowclass);
    tryreport($NCapi, $params);
}

$linknames = array('LX1', 'LX2', 'LX3', 'LX4', 'LX0');
$sources = array('NA1', 'NA1', 'NA2', 'NA3', 'NA100');
$targets = array('NA2', 'NA3', 'NA3', 'NA4', 'NA2');
$linkclasses = array('LINK_X', 'LINK_X', 'LINK_Y', 'LINK_X', 'LINK_X');
for ($i = 0; $i < count($linknames); $i++) {
    $nowname = $linknames[$i];
    $nowclass = $linkclasses[$i];
    echo "Creating link $nowname ($nowclass) from $sources[$i] to $targets[$i]";
    $params = array('controller' => 'NCGraphs', 'action' => 'createNewLink',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'link_name' => $nowname, 'link_title' => "Link $nowname",
        'class_name' => $nowclass, 'source_name' => $sources[$i], 'target_name' => $targets[$i]);
    tryreport($NCapi, $params);
}

/* --------------------------------------------------------------------------
 * Fetching nodes and links
 * -------------------------------------------------------------------------- */

echo "getting all nodes:";
$params = array('controller' => 'NCGraphs', 'action' => 'getAllNodes',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu');
$result = tryreport($NCapi, $params, true);
//print_r($result);

echo "getting all links:";
$params = array('controller' => 'NCGraphs', 'action' => 'getAllLinks',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu');
$result = tryreport($NCapi, $params, true);
//print_r($result);



?>
