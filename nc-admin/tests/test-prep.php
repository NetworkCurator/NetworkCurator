<?php

// small script included to create db connection and fetch admin data 
// (used in multiple test scripts)


/* --------------------------------------------------------------------------
 * Prep - get admin user information
 * -------------------------------------------------------------------------- */

include_once "../config/nc-config.php";
include_once "../../nc-api/helpers/nc-db.php";
include_once "../../nc-api/helpers/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";

$db = connectDB(NC_DB_SERVER, NC_DB_NAME, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD);

// get information about the admin user
$sql = "SELECT user_id, user_pwd, user_extpwd FROM " . NC_TABLE_USERS . "
          WHERE BINARY user_id='admin'";
$stmt = $db->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$uname = 'admin';
$upw = $row['user_extpwd'];


// set up the API and a core array of parameters
// echo "using ".NC_APP_ID." and ".NC_APP_KEY." ".NC_API_PATH."\n";
// echo "\n\n";
$NCapi = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);


// helper function to attempt api requests and provide feedback
function tryreport($api, $params, $return = false) {
    $result = null;
    try {
        $result = $api->sendRequest($params);
        echo "\tok\n";
    } catch (Exception $ex) {
        echo "\tErr: ".$ex->getMessage()."\n";
    }
    
    if ($result) {
        return $result;
    } else {
        return null;
    }
}


?>