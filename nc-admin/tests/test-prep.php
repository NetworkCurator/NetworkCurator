<?php

// small script included to create db connection and fetch admin data 
// (used in multiple test scripts)


/* --------------------------------------------------------------------------
 * Prep - get admin user information
 * -------------------------------------------------------------------------- */

include_once "../config/nc-config-local.php";
include_once "../../nc-api/helpers/nc-db.php";
include_once "../../nc-api/helpers/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";

$db = connectDB(NC_DB_SERVER, NC_DB_NAME, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD);

// get information about the admin user
$sql = "SELECT user_id, user_pwd, user_extpwd FROM " . NC_TABLE_USERS . "
          WHERE BINARY user_id='admin'";
$stmt = $db->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$uid = 'admin';
$upw = $row['user_extpwd'];


// set up the API and a core array of parameters
// echo "using ".NC_APP_ID." and ".NC_APP_KEY." ".NC_API_PATH."\n";
// echo "\n\n";
$NCapi = new GeneralApiCaller(NC_APP_ID, NC_APP_KEY, NC_API_PATH);


// helper function to attempt api requests and provide feedback
// $api - instance of GeneralApiCaller
// $params - array with data for the ApiCaller
// $success - boolean (true to designated an expected success, false to signal expected exception)
// $return - boolean (true to return data from the API call)
function tryreport($api, $params, $success=true, $return = false) {
    $result = null;
        
    try {
        $result = $api->sendRequest($params);
        if ($success) {
            echo "\tok\n";
        } else {
            echo "\tfail\n";
        }        
    } catch (Exception $ex) {
        echo "\t[Err: ".$ex->getMessage()."]";
        if ($success) {
            echo "\tfail\n";
        } else {
            echo "\tok\n";
        }        
    }
    
    if ($result) {
        return $result;
    } else {
        return null;
    }
}

?>
