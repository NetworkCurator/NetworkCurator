<?php
/*
 * Functions that supply miscellaneus ui helpers
 * 
 */


/**
 * concatenames user names from an array
 * 
 * @param type $a
 * @return type
 */
function ui_listnames($a) {    
    $ans = "";
    for ($x=0; $x<count($a); $x++) {
        if ($x>0) {
         $ans .=", ";   
        }
        $ans .= ncFullname($a[$x]);
    }
    return $ans;
}

?>
