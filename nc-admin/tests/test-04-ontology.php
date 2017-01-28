<?php

/*
 * Post-installation script that tests API controller: NCOntology
 * (For debugging and testing only)
 * 
 * The tests that fetch ontology information report "ok" as long as there is no
 * error/exception throw. To check if the db actually contains the proper data, 
 * check the result manually
 * 
 */

echo "\n";
echo "test-04-ontology: testing creating and updating ontology classes\n\n";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Create some classes for the zulu network
 * -------------------------------------------------------------------------- */

$nodeclasses = array('NODE_A'=>true, 'NODE_B'=>true, 'NODE_C'=>true, 'Q'=>false);
foreach ($nodeclasses as $abc => $expected) {
    echo "Creating node class $abc";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu', 'parent' => '', 'connector' => 0,
        'directional' => 0, 'name' => $abc, 'title'=>$abc, 'abstract'=>'', 'content'=>'', 
        'defs'=>'');
    tryreport($NCapi, $params, $expected);
}


$linkclasses = array('LINK_X'=>true, 'LINK_Y'=>true, 'LINK_C'=>true, 'NODE_A'=>false);
foreach ($linkclasses as $abc => $expected) {
    echo "Creating link class $abc";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network' => 'nc-test-zulu', 'parent' => '', 'connector' => 1,
        'directional' => 0, 'name' => $abc, 'title'=>$abc, 'abstract'=>'', 'content'=>'');
    tryreport($NCapi, $params, $expected);
}


/* --------------------------------------------------------------------------
 * Try updating classes
 * -------------------------------------------------------------------------- */

echo "Updating NODE_C into NODE_B;NODE_C2: ";
$params = array('controller' => 'NCOntology', 'action' => 'updateClass',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu', 'target' => 'NODE_C', 'name' => 'NODE_C2',
    'title'=>'', 'abstract'=>'', 'content'=>'',
    'parent' => 'NODE_B', 'connector' => 0, 'directional' => 0,
    'status' => 1, 'defs'=>'');
tryreport($NCapi, $params, true);

echo "Deactivating LINK_C: ";
$params = array('controller' => 'NCOntology', 'action' => 'removeClass',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu', 'name' => 'LINK_C');
tryreport($NCapi, $params, true);



/* --------------------------------------------------------------------------
 * Try fetching ontologies
 * -------------------------------------------------------------------------- */

echo "Fetching node ontology: ";
$params = array('controller' => 'NCOntology', 'action' => 'getNodeOntology',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu');
$result = tryreport($NCapi, $params, true, true);
//print_r($result);


echo "Fetching ontology disctionary: ";
$params = array('controller' => 'NCOntology', 'action' => 'getOntologyDictionary',
    'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
    'network' => 'nc-test-zulu');
$result = tryreport($NCapi, $params, true, true);
//print_r($result);


?>