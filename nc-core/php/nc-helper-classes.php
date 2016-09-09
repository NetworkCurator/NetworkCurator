<?php

/*
 * Helper functions to deal with node and link class structure
 */


/**
 * 
 * @param array $classarray
 * 
 * an array with class data as output by getNodeClasses or getLinkClasses
 * 
 * @return array
 * 
 * an array with classnames only
 */
function getFlatClassList($classarray) {
    
    // transfer just the class names from the complicated classarray into a simpler one
    $result = [];
    foreach ($classarray as $nowclass) {
        $result[] = $nowclass['class_name'];
    }
    sort($result);
    
    // prepend one elemnt to the array with "None"
    array_unshift($result, "[None]");
    
    return $result;
}

?>