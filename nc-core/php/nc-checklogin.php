<?php

/*
 * Function for comparing session and cookie data
 *  
 * Functions assume that the NC configuration definitions are already loaded
 *  
 */

/**
 * Check if the user has already previously logged in, and a session with the
 * user has already been established. 
 * 
 * Also checks to see if user has been remembered.
 * 
 * If so, the database is queried to make sure of the user's 
 * authenticity. Returns true if the session user is logged in.
 * 
 * @param $NCapi - an instance of the NC api caller
 * 
 */
function ncCheckLoginDeprecated($NCapi) {

    // default state is "guest"
    $g = "guest";
    $tim = time() + (3600 * 24 * 7);
    $firstname = $uname = $upw = $g;
    $lastname = $middlename = "";

    // check if a user has already been remembered
    if (isset($_SESSION['uname']) && isset($_SESSION['upw'])) {
        // Username and password have been set.        
        $uname = $_SESSION['uname'];
        $upw = $_SESSION['upw'];
        $firstname = $_SESSION['firstname'];
        $middlename = $_SESSION['middlename'];
        $lastname = $_SESSION['lastname'];
    } else {
        if (isset($_COOKIE['nc_uname']) && isset($_COOKIE['nc_upw'])) {
            $uname = $_COOKIE['nc_uname'];
            $upw = $_COOKIE['nc_upw'];
            $firstname = $_COOKIE['nc_firstname'];
            $middlename = $_COOKIE['nc_middlename'];
            $lastname = $_COOKIE['nc_lastname'];
        } else {
            $_SESSION['uname'] = $g;
            $_SESSION['upw'] = $g;
            $_SESSION['firstname'] = $g;
            $_SESSION['middlename'] = "";
            $_SESSION['lastname'] = "";
            $uname = $upw = $g;
            return false;
        }
    }

    // confirm the existence of the user using an API call
    //echo "Using API in nc-checklogin<br/>";    
    $apiparams = array('user_extpwd' => $upw);
    $userconfirmed = $NCapi->sendReq($uname, "NCUsers", "confirm", $apiparams);

    // update the cookies for logged-in users
    if ($userconfirmed) {
        if ($userconfirmed['success'] === true) {
            $_SESSION['uname'] = $uname;
            $_SESSION['upw'] = $upw;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['middlename'] = $middlename;
            $_SESSION['lastname'] = $lastname;
            setcookie("nc_uname", $uname, $tim, "/");
            setcookie("nc_firstname", $firstname, $tim, "/");
            setcookie("nc_middlename", $middlename, $tim, "/");
            setcookie("nc_lastname", $lastname, $tim, "/");
            if ((isset($_SESSION['remember']) && $_SESSION['remember'] == 1) || (isset($_COOKIE['nc_remember']) && $_COOKIE['nc_remember'] == 1)) {
                setcookie("nc_upw", $upw, $tim, "/");
                $_SESSION['remember'] = 1;
            }
            return true;
        }
    }

    return false;
}

?>
