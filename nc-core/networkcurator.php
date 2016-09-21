<?php

/**
 * This acts like an api in that it receives requests and responds with answers.
 * 
 * It is meant to accept requests from javascript on the web in
 * unencrypted state. This script on the server knows about the app keys
 * and thus prepares the request for the nc-api.php
 * 
 */
    
    
// load the settings for the website
include_once "../nc-admin/config/nc-config.php";

// load the api caller class that will handle interactions with nc-api
include_once "../nc-api/helpers/GeneralApiCaller.php";

    
try {

    // collect information about the user from the session
    session_start();
    
    // the session is likely to be set if the request comes from the website
    // in any case, make sure the uid and upw are set
    if (!isset($_SESSION['uid']) || !isset($_SESSION['upw'])) {
        throw new Exception("Session has not been initialized");
    }
    
    // small checks on the request (more checks within nc-api.php)
    $params = (array) $_REQUEST;
    if (isset($params['user_id'])) {
        if ($params['user_id'] != $_SESSION['uid']) {
            throw new Exception("Inconsistent user_id / session combination");
        }
    } else {
        $params['user_id'] = $_SESSION['uid'];
    }
    $params['user_extpwd'] = $_SESSION['upw'];
    $params['source_ip'] = $_SERVER['REMOTE_ADDR'];
    
    // create a generic ApiCaller
    $NCapi = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);

    // perform a first check on user identity
    $apiparams = array('user_extpwd' => $_SESSION['upw']);
    $userconfirmed = $NCapi->sendReq($_SESSION['uid'], "NCUsers", "confirm", $apiparams);        
    if (!$userconfirmed) {
        throw new Exception("Failed user confirmation");
    }
    
    // perform the api call using the generic ApiCaller    
    $result = $NCapi->sendRequest($params);

    // in the special case of a successful login, the session should be updated
    if ($params['controller'] === "NCUsers" && $params['action'] === "verify") {
        if ($result) {
            // user login was successful, so here update the session info
            include_once "php/nc-sessions.php";
            ncSignin($result['user_id'], $result['user_extpwd'], $result['user_firstname'], $result['user_lastname'], $params['remember']);
            $result['user_extpwd'] = '';
        }
    }

    $ans = array();
    $ans['success'] = true;
    $ans['data'] = $result;    
    echo json_encode($ans);
    exit();
    
} catch (Exception $e) {
    $ans = array();
    $ans['success'] = false;
    $ans['errormsg'] = $e->getMessage();
    echo json_encode($ans);
    exit();
}
?>
