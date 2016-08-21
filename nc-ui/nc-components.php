<?php
/*
 * Functions that supply page components
 * 
 */

/**
 * Miscellaneous
 */
function ui_misc() {
    include "nc-components/ui-misc.php";
}

/**
 *  provides a form asking for user data
 */
function ui_newuser() {
    include "nc-components/ui-newuser-form.php";
}

/**
 * provides a form asking details for a new network
 */
function ui_newnetwork() {
    include "nc-components/ui-newnetwork-form.php";
}


/**
 *  provides a form asking for user data
 */
function ui_login() {
    include "nc-components/ui-login-form.php";
}

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
