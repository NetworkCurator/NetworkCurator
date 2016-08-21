<?php

/*
 * Helper functions that use session data
 * 
 */

/**
 * Performs user signin.
 * Assumes verification is already performed
 * Encodes data into the session and cookies
 * 
 * @param type $uid
 * @param type $upw
 * @param type $ufirst
 * @param type $ulast
 * @param type $remember
 */
function ncSignin($uid, $upw, $ufirst, $ulast, $remember) {
    $_SESSION['uid'] = $uid;
    $_SESSION['upw'] = $upw;
    $_SESSION['firstname'] = $ufirst;
    $_SESSION['lastname'] = $ulast;
    $_SESSION['remember'] = 0;

    // for expiry, use 7 days
    $tim = time() + (7 * 24 * 3600);
    setcookie("nc_uid", $_SESSION['uid'], $tim, "/");
    setcookie("nc_firstname", $_SESSION['firstname'], $tim, "/");
    setcookie("nc_lastname", $_SESSION['lastname'], $tim, "/");
    if ($remember === true) {
        setcookie("nc_upw", $_SESSION['upw'], $tim, "/");
        $_SESSION['remember'] = 1;
    }
}

/**
 * Destroys information in the session and in cookies
 * 
 */
function ncSignout() {
    // destroy the session    
    session_start();
    $_SESSION = array();
    session_destroy();

    // start a new session as a guest
    session_start();
    $_SESSION['uid'] = "guest";
    $_SESSION['upw'] = "guest";
    $_SESSION['firstname'] = "guest";
    $_SESSION['lastname'] = "";

    // remove the site cookies
    $cookies = array("nc_uid", "nc_upw", "nc_firstname", "nc_lastname", "nc_remember");
    foreach ($cookies as $nowcookie) {
        if (isset($_COOKIE[$nowcookie])) {
            setcookie($nowcookie, "", time() - 1000000, "/");
        }
    }
}

/**
 * Get a string with the usernames's full name from the session
 *
 * @return string 
 */
function ncUserFullname() {
    if (isset($_SESSION['firstname'])) {
        $firstname = $_SESSION['firstname'];
        $middlename = $_SESSION['middlename'];
        $lastname = $_SESSION['lastname'];
        if ($middlename !== "") {
            return "$firstname $middlename $lastname";
        } else {
            return "$firstname $lastname";
        }
    } else {
        return "guest";
    }
}

/**
 * provides the name of the site
 */
function ncSiteName() {
    return NC_SITE_NAME;
}

/**
 * Checks current session status for guest status
 * 
 * @return boolean
 * 
 */
function ncIsUserSignedIn() {
    if (isset($_SESSION['uid'])) {
        if ($_SESSION['uid'] !== "guest") {
            return true;
        }
    }
    return false;
}


?>
