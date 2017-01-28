<?php

/*
 * Post-installation script that tests API controller: NCGraph
 * (For debugging and testing only)
 * 
 */

echo "\n";
echo "test-05-create: testing creating and updating graph objects\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Create some nodes and links for the zulu network
 * -------------------------------------------------------------------------- */

$nodenames = array('NA1', 'NA2', 'NA3', 'NA4', 'NA1', 'NA0');
$nodeclasses = array('NODE_A', 'NODE_A', 'NODE_B', 'NODE_A', 'NODE_B', 'NODE_BAD');
// the last two are expected to fail because of duplicate name and nonexistent ontology class
$nodeexpected = array(true, true, true, true, false, false);
for ($i = 0; $i < count($nodenames); $i++) {
    $nowname = $nodenames[$i];
    $nowclass = $nodeclasses[$i];
    echo "Creating node $nowname ($nowclass)";
    $params = array('controller' => 'NCGraphs', 'action' => 'createNewNode',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu',
        'name' => $nowname, 'title' => "Node $nowname", 'abstract' => '', 'content' => '',
        'class' => $nowclass);
    tryreport($NCapi, $params, $nodeexpected[$i]);
}

$linknames = array('LX1', 'LX2', 'LX3', 'LX4', 'LX0');
$sources = array('NA1', 'NA1', 'NA2', 'NA3', 'NA100');
$targets = array('NA2', 'NA3', 'NA3', 'NA4', 'NA2');
$linkclasses = array('LINK_X', 'LINK_X', 'LINK_Y', 'LINK_X', 'LINK_X');
$linkexpected = array(true, true, true, true, false);
for ($i = 0; $i < count($linknames); $i++) {
    $nowname = $linknames[$i];
    $nowclass = $linkclasses[$i];
    echo "Creating link $nowname ($nowclass) from $sources[$i] to $targets[$i]";
    $params = array('controller' => 'NCGraphs', 'action' => 'createNewLink',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu',
        'name' => $nowname, 'title' => "Link $nowname", 'abstract' => '', 'content' => '',
        'class' => $nowclass, 'source' => $sources[$i], 'target' => $targets[$i]);
    tryreport($NCapi, $params, $linkexpected[$i]);
}

/* --------------------------------------------------------------------------
 * Fetching nodes and links
 * -------------------------------------------------------------------------- */

echo "Fetching all nodes: ";
$params = array('controller' => 'NCGraphs', 'action' => 'getAllNodes',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu');
$allnodes = tryreport($NCapi, $params, true, true);
//print_r($allnodes);

echo "Fetching all links: ";
$params = array('controller' => 'NCGraphs', 'action' => 'getAllLinks',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu');
$alllinks = tryreport($NCapi, $params, true, true);
//print_r($alllinks);


/* --------------------------------------------------------------------------
 * updating node classes and ownership
 * -------------------------------------------------------------------------- */

foreach ($allnodes as $key => $value) {
    $nownode = $allnodes[$key]['name'];
    $nowid = $allnodes[$key]['id'];
    $nowclass = $allnodes[$key]['class'];
    echo "Updating node $nownode to class NODE_B: ";
    $params = array('controller' => 'NCGraphs', 'action' => 'updateClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu', 'target_id' => $nowid, 'class' => 'NODE_B');
    $result = tryreport($NCapi, $params, $nowclass != "NODE_B", true);
    
    echo "Updating node $nownode to owner alpha: ";
    $params = array('controller' => 'NCGraphs', 'action' => 'updateOwner',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu', 'target_id' => $nowid, 'owner' => 'alpha');
    $result = tryreport($NCapi, $params, true, true);
}

?>
