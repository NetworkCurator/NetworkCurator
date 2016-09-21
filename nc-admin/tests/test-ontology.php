<?php

/*
 * Tests for creating/adjusting ontology classes 
 * 
 */


echo "\n";
echo "NetworkCurator - some tests for NCOntology \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Create some classes for the zulu network
 * -------------------------------------------------------------------------- */


$newclasses = array('NODE_A', 'NODE_B', 'NODE_C', 'Q');
foreach ($newclasses as $abc) {
    echo "Creating node class $abc";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'parent_name' => '', 'connector' => 0,
        'directional' => 0, 'class_name' => $abc);
    tryreport($NCapi, $params);
}


$newclasses = array('LINK_X', 'LINK_Y', 'LINK_C', 'NODE_A');
foreach ($newclasses as $abc) {
    echo "Creating link class $abc";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'parent_name' => '', 'connector' => 1,
        'directional' => 0, 'class_name' => $abc);

    tryreport($NCapi, $params);
}


/* --------------------------------------------------------------------------
 * Try updating classes
 * -------------------------------------------------------------------------- */

echo "Updating NODE_C into NODE_B:NODE_C2";
$params = array('controller' => 'NCOntology', 'action' => 'updateClass',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu', 'target_name' => 'NODE_C', 'class_name' => 'NODE_C2',
    'parent_name' => 'NODE_B', 'connector' => 0, 'directional' => 0,
    'class_status' => 1);
tryreport($NCapi, $params);

echo "Deactivating LINK_C";
$params = array('controller' => 'NCOntology', 'action' => 'removeClass',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu', 'class_name' => 'LINK_C');
tryreport($NCapi, $params);



/* --------------------------------------------------------------------------
 * Try fetching ontologies
 * -------------------------------------------------------------------------- */

echo "Fetching node ontology";
$params = array('controller' => 'NCOntology', 'action' => 'getNodeOntology',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu');
$result = tryreport($NCapi, $params, true);
//print_r($result);


echo "Fetching ontology disctionary";
$params = array('controller' => 'NCOntology', 'action' => 'getOntologyDictionary',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network_name' => 'net-zulu');
$result = tryreport($NCapi, $params, true);
//print_r($result);


?>
