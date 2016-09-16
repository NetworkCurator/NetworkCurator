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
    echo "Creating node class $abc\n";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'parent_id' => '', 'connector' => 0,
        'directional' => 0, 'class_name' => $abc);
    try {
        $ok = $NCapi->sendRequest($params);
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage();
        echo "\n";
    }
}


$newclasses = array('LINK_X', 'LINK_Y', 'LINK_C', 'NODE_A');
foreach ($newclasses as $abc) {
    echo "Creating link class $abc\n";
    $params = array('controller' => 'NCOntology', 'action' => 'createNewClass',
        'user_id' => 'admin', 'user_extpwd' => $upw, 'user_ip' => 'install-testdata',
        'network_name' => 'net-zulu', 'parent_id' => '', 'connector' => 1,
        'directional' => 0, 'class_name' => $abc);
    try {
        $ok = $NCapi->sendRequest($params);
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage();
        echo "\n";
    }
}



?>
