<?php

/*
 * API server 
 * 
 * 
 * This code is modeled on a tutorial here: (with modifications)
 * http://code.tutsplus.com/tutorials/creating-an-api-centric-web-application--net-23417
 *
 * 
 * This api does not do any testing for user identity. This is assumed to be
 * performed in networkcurator.php.
 * 
 */

// some settings for the application
$nowdir = dirname(__FILE__);
include_once $nowdir . "/../nc-admin/config/nc-config-local.php";
include_once $nowdir . "/../nc-admin/config/nc-constants.php";
// connectivity to the database and general
include_once $nowdir . "/helpers/nc-db.php";
include_once $nowdir . "/helpers/nc-generic.php";
include_once $nowdir . "/helpers/NCLogger.php";

// object that will hold answer of the api call
$ans = array();

try {
    
    // connect to the database    
    $db = connectDB(NC_DB_SERVER, NC_DB_NAME, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD);

    // get the settings from the API request    
    $request = base64_decode($_REQUEST['data']);
    if ($_REQUEST['app_id'] !== NC_APP_ID) {
        throw new Exception("Invalid app id");
    }

    // decrypt the request into a $param array        
    //$params = json_decode($request);    
    $params = json_decode(trim(
                    mcrypt_decrypt(MCRYPT_RIJNDAEL_256, NC_APP_KEY, $request, MCRYPT_MODE_ECB)));
    if ($params == false) {
        throw new Exception('Invalid request - decryption failed');
    }   
    if (isset($params->controller) == false || isset($params->action) == false) {
        throw new Exception('Invalid request - missing controller or action');
    }
    if (isset($params->user_id) == false) {
        throw new Exception('Invalid request - missing userid');
    }
    $params = (array) $params;
    
    // record the ip address of the source server
    $sourceaddr = $_SERVER['REMOTE_ADDR'];
    if (isset($request['source_ip'])) {
        $sourceaddr = $request['source_ip'];        
    }
    $params['source_ip'] = $sourceaddr;

    // get the controller and requested action
    $controller = $params['controller'];
    $action = $params['action'];
    unset($params['controller']);
    unset($params['action']);        
    
    // create instance of the controller
    include_once "controllers/NCUsers.php";    
    $controllerfile = "controllers/{$controller}.php";
    if (file_exists($controllerfile)) {
        include_once $controllerfile;
    } else {
        throw new Exception("Invalid controller $controller");
    }
    $nowcontroller = new $controller($db, $params);
    
    // check that action is not one of the `private functions`    
    if (in_array($action, get_class_methods('NCLogger'))) {
        throw new Exception("Invalid action $action");
    }
    // check if action is defined in the controller
    if (method_exists($nowcontroller, $action) === false) {
        throw new Exception("Invalid action $action");
    }
    // execute the requested action in the controller    
    $ans['data'] = $nowcontroller->$action();

    // log most actions, except user confirmation    
    if ($action == "verify") {
        if (isset($params['target_password'])) {            
            unset($params['target_password']);
        }

        $logger = new NCLogger($db, ["user_id"=> $params['user_id']]);
        $logger->logAction($params['user_id'], $sourceaddr, $controller, $action, json_encode($params));
    }

    // if reached here, presumably the controlled finished correctly
    $ans['success'] = true;
    $db = null;
} catch (Exception $e) {
    $ans = array();
    $ans['success'] = false;
    $ans['errormsg'] = $e->getMessage();
}

echo json_encode($ans);
exit();
?>
