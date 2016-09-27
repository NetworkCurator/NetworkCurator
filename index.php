<?php

/**
 * Main index for the NetworkCurator system. 
 * All user interaction should got through here.
 * 
 */
/* --------------------------------------------------------------------------
 * Housekeeping 
 * -------------------------------------------------------------------------- */

// load the settings for the website
include_once "nc-admin/config/nc-config.php";
include_once "nc-admin/config/nc-constants.php";

// load helper functions
$PP = $_SERVER['DOCUMENT_ROOT'] . NC_PHP_PATH;
$UP = $_SERVER['DOCUMENT_ROOT'] . NC_UI_PATH;
include_once "nc-api/helpers/GeneralApiCaller.php";
include_once "nc-api/helpers/nc-generic.php";
include_once $PP . "/NCApiCaller.php";
include_once $PP . "/nc-sessions.php";
include_once $PP . "/nc-helpers.php";

// get two common fields from the url
$page = '';
if (isset($_REQUEST['page'])) {
    $page = $_REQUEST['page'];
}
$network = '';
if (isset($_REQUEST['network'])) {
    $network = $_REQUEST['network'];
    $page = 'network';
}

// collect information about the user from the session
session_start();
if (!isset($_SESSION['uid'])) {
    $uid = "guest";
    $upw = "guest";
} else {
    $uid = $_SESSION['uid'];
    $upw = $_SESSION['upw'];
}
$NCapi = new NCApiCaller($uid, $upw);
try {
    $userin = $NCapi->checkLogin();
} catch (Exception $ex) {
    ncSignout();
    header("Refresh: 0; ?page=front");
    exit();
}
if (!$userin) {
    $uid = "guest";
}
$userip = $_SERVER['REMOTE_ADDR'];

// determine permission levels for this user
try {    
    $upermissions = $NCapi->querySelfPermissions($network);
} catch (Exception $ex) {
    $upermissions = 0;
}
$iscurator = 0 + ($upermissions >= NC_PERM_CURATE);
$iscommentator = 0 + ($upermissions >= NC_PERM_COMMENT);
$iseditor = 0 + ($upermissions >= NC_PERM_EDIT);


/* --------------------------------------------------------------------------
 * Create user-viewable page 
 * -------------------------------------------------------------------------- */

if ($page == "logout") {
    ncSignout();
    header("Refresh: 0; ?page=front");
}

// create a basic structure for all pages with header, navbar
include_once "nc-ui/nc-header.php";
include_once "nc-ui/nc-navbar.php";

// the middle portion of the page is generated by scripts in nc-ui
if ($page == "login" || $page == "admin") {
    // these are pages that require only a user id
    include_once "nc-ui/nc-ui-$page.php";
} else if ($page == "network" && $network) {
    // these are pages that require a network name
    include_once "nc-ui/nc-ui-$page.php";
} else if ($page == "front" || $page == '') {
    include_once "nc-ui/nc-ui-front.php";
} else {
    include_once "nc-ui/nc-ui-custom.php";
}

// the footer is common to all pages
include_once "nc-ui/nc-footer.php";


exit();
?>
