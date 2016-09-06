<?php

// Installation script for a NetworkCurator database
//

echo "\n";
echo "NetworkCurator installation\n\n";
include "../../nc-api/helpers/nc-generic.php";

// load the settings (also local settings)
$localfile = "install-settings-local.php";
if (file_exists($localfile)) {
    include $localfile;
}
include "install-settings.php";


/* --------------------------------------------------------------------------
 * Prep - helper functions and helper variables
 * -------------------------------------------------------------------------- */

function sqlreport($db, $s) {
    try {
        $db->query($s);
        echo "\tok\n";
    } catch (Exception $ex) {
        echo "\tError: " . $ex->getMessage();
    }
}

// Helper definitions for sql columns
$vc24col = " VARCHAR(24) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$vc32col = " VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$vc64col = " VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$vc128col = " VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$vc256col = " VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$textcol = " TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
$statuscol = " TINYINT NOT NULL DEFAULT 1";
$dblcol = " DOUBLE NOT NULL DEFAULT 0.0";
$datecol = " DATETIME NOT NULL";


/* --------------------------------------------------------------------------
 * Start installation
 * -------------------------------------------------------------------------- */

try {

    $dbroot = new PDO('mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_ROOT, DB_ROOT_PASSWD);
    $dbroot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbroot->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    echo "Dropping existing database:";
    $sql = "DROP DATABASE IF EXISTS " . DB_NAME . " ";
    sqlreport($dbroot, $sql);

    echo "Creating database:\t";
    $sql = "CREATE DATABASE " . DB_NAME . " ";
    $sql .= "DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    sqlreport($dbroot, $sql);

    $dbroot = null;
} catch (Exception $e) {
    echo "DROP/CREATE operations failed: " . $e->getMessage();
    echo "\n\n";
}


/* --------------------------------------------------------------------------
 * Create tables
 * -------------------------------------------------------------------------- */

$db = new PDO('mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_ADMIN, DB_ADMIN_PASSWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

echo "Dropping existing tables:";
$tp = "" . DB_TABLE_PREFIX . "_";
$alltabs = array("users", "networks", "permissions",
    "nodes", "links", "annotations", "annotation_log");
$sql = "DROP TABLE IF EXISTS " . $tp . implode(", " . $tp, $alltabs);
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The users table will hold information about registered users

$tabname = $tp . "users";
echo "Creating table $tabname:";
$sql = "CREATE TABLE $tabname (
  datetime $datecol,
  user_id $vc32col,
  user_firstname $vc64col,
  user_middlename $vc64col, 
  user_lastname $vc64col,
  user_email $vc256col,
  user_pwd $vc256col, 
  user_extpwd $vc256col,   
  user_status $statuscol,
  PRIMARY KEY (`user_id`)
  ) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The permissions table will hold network and user permissions
// e.g. is a given user permitted to view/annotate a given network?

$tabname = $tp . "permissions";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (
  user_id $vc32col,
  network_id $vc32col,    
  permissions INT NOT NULL DEFAULT 0,
  UNIQUE KEY user_network (user_id, network_id),
  KEY network_id (network_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The networks table will hold a list of all available networks

$tabname = $tp . "networks";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,       
  owner_id $vc32col,  
  PRIMARY KEY (network_id)  
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The nodes table will hold all nodes across all networks

$tabname = $tp . "nodes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,
  node_id $vc32col,
  class_id $vc32col,
  node_name $vc64col,  
  node_value $dblcol,
  node_valueunit $vc24col,
  node_score $dblcol,
  node_status $statuscol,
  PRIMARY KEY (node_id),
  KEY class_id (class_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The links table will hold all links across all networks

$tabname = $tp . "links";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,
  link_id $vc32col,
  from_id $vc32col,
  to_id $vc32col,
  class_id $vc32col,
  link_name $vc64col,  
  link_value $dblcol,
  link_valueunit $vc24col,
  link_score $dblcol,
  link_status $statuscol,
  PRIMARY KEY (link_id),
  KEY from_id (from_id),
  KEY to_id (to_id),
  KEY class_id (network_id, class_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The classes table will hold ontologies for nodes and links
// add constraints on link from/to classes?

$tabname = $tp . "classes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,
  class_id $vc32col,       
  parent_id $vc32col,
  connector TINYINT NOT NULL DEFAULT 0,
  directional TINYINT NOT NULL DEFAULT 0,
  class_score $dblcol,
  class_status $statuscol,
  PRIMARY KEY (class_id),
  KEY network_id (network_id, parent_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The annotations table will hold comments, subcomments for all components

$tabname = $tp . "anno_text";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  datetime DATETIME NOT NULL,
  anno_id $vc32col,
  anno_level INT NOT NULL DEFAULT 0,
  owner_id $vc32col,
  user_id $vc32col,  
  network_id $vc32col,  
  root_id $vc32col,
  parent_id $vc32col,  
  anno_text $textcol,  
  anno_status $statuscol,
  KEY level (network_id, anno_level),
  KEY anno_id (anno_id)  
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


$tabname = $tp . "anno_numeric";
echo "Creating table $tabname:";
$sql = "CREATE TABLE $tabname (  
  datetime DATETIME NOT NULL,
  anno_id $vc32col,
  anno_level INT NOT NULL DEFAULT 0,
  owner_id $vc32col,
  user_id $vc32col,  
  network_id $vc32col,  
  root_id $vc32col,
  parent_id $vc32col,      
  anno_value $dblcol,
  anno_valueunit $vc24col,  
  anno_status $statuscol,
  KEY level (network_id, anno_level),
  KEY anno_id (anno_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);



// -----------------------------------------------------------------------------
// The datafiles table will hold metadata for uploaded files

$tabname = $tp . "datafiles";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  datetime DATETIME NOT NULL,
  network_id $vc32col,  
  user_id $vc32col,  
  original_filename $vc256col,
  filename $vc64col,  
  filetype $vc24col,
  filesize BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (filename),
  KEY network_id (network_id)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The activity table will hold a summary of changes to the network
// This will be viewable by all users

$tabname = $tp . "activity";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (
  datetime $datecol,
  user_id $vc32col,
  network_id $vc32col,    
  action $vc64col,
  target_name $vc32col,  
  value $textcol,
  KEY datetime (datetime),
  KEY network_id (network_id, datetime)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


// -----------------------------------------------------------------------------
// The log table will hold activity at the site level
// This information will be visible in the db, not on the website

$tabname = $tp . "log";
echo "Creating table $tabname:\t";
$sql = "CREATE TABLE $tabname (
  datetime $datecol,
  user_id $vc32col,
  user_ip $vc128col,
  controller $vc64col,
  action $vc64col,
  value $textcol,
  KEY user_id (user_id, datetime)
) COLLATE utf8_unicode_ci";
sqlreport($db, $sql);


/* --------------------------------------------------------------------------
 * Create required users
 * -------------------------------------------------------------------------- */

// Creating users here - exceptionally "manually" through a direct table insert
// On the web app, all other users should be inserted via the NCUsers class
echo "\nCreating users:\t\t";
$userstable = $tp . "users";
$adminpass = password_hash(NC_SITE_ADMIN_PASSWORD, PASSWORD_BCRYPT);
$adminextp = md5(makeRandomHexString(60));
$sql = "INSERT INTO $userstable 
            (datetime, user_id, user_pwd, user_extpwd, 
            user_firstname, user_middlename, user_lastname,
            user_email, user_status) VALUES 
            (UTC_TIMESTAMP(), 'admin',  '$adminpass', '$adminextp', 
                'Administrator', '', '', '', 1) , 
            (UTC_TIMESTAMP(), 'guest',  '', 'guest', 
            'Guest', '', '', '', 1)";
sqlreport($db, $sql);


// Create an icon for the admin
$img = imagecreate(48, 48);
$imgbg = imagecolorallocate($img, 0, 0, 0);
$imgfile = "../../nc-data/users/admin.png";
imagepng($img, $imgfile);        
        


/* --------------------------------------------------------------------------
 * Finish up with activity log
 * -------------------------------------------------------------------------- */

echo "\nLogging installation (1/2):";
$logtable = $tp . "activity";
$sql = "INSERT INTO $logtable 
          (datetime, user_id, network_id, target_name, action, value) VALUES 
          (UTC_TIMESTAMP(), 'admin', '', '',
          'installed the NetworkCurator database', '')";
sqlreport($db, $sql);


echo "Logging installation (2/2):";
$logtable = $tp . "log";
$sql = "INSERT INTO $logtable 
          (datetime, user_id, action, value) VALUES 
          (UTC_TIMESTAMP(), 'admin', 'install', '')";
sqlreport($db, $sql);

// close the connection
$db = null;
echo "\n";



/* --------------------------------------------------------------------------
 * Create configuration files for the site
 * -------------------------------------------------------------------------- */

// find the path for the web server
$ncpath = NC_PATH;
$ncappkey = makeRandomHexString(32);

// Create a configuration file for the 
echo "Writing site config file:\t";
$myconf = "<?php\n";
$myconf .= "\n// Configuration for connecting to the database\n";
$myconf .= "define('NC_DB_SERVER',\t\t'" . DB_SERVER . "');\n";
$myconf .= "define('NC_DB_NAME',\t\t'" . DB_NAME . "');\n";
$myconf .= "define('NC_DB_ADMIN',\t\t'" . DB_ADMIN . "');\n";
$myconf .= "define('NC_DB_ADMIN_PASSWD',\t'" . DB_ADMIN_PASSWD . "');\n";
$myconf .= "\n// Configuration for accessing data tables\n";
$myconf .= "define('NC_TABLE_ACTIVITY',\t'" . DB_TABLE_PREFIX . "_activity');\n";
$myconf .= "define('NC_TABLE_ANNOTEXT',\t'" . DB_TABLE_PREFIX . "_anno_text');\n";
$myconf .= "define('NC_TABLE_ANNONUM',\t'" . DB_TABLE_PREFIX . "_anno_numeric');\n";
$myconf .= "define('NC_TABLE_CLASSES',\t'" . DB_TABLE_PREFIX . "_classes');\n";
$myconf .= "define('NC_TABLE_LINKS',\t'" . DB_TABLE_PREFIX . "_links');\n";
$myconf .= "define('NC_TABLE_LOG',\t\t'" . DB_TABLE_PREFIX . "_log');\n";
$myconf .= "define('NC_TABLE_NETWORKS',\t'" . DB_TABLE_PREFIX . "_networks');\n";
$myconf .= "define('NC_TABLE_NODES',\t'" . DB_TABLE_PREFIX . "_nodes');\n";
$myconf .= "define('NC_TABLE_PERMISSIONS',\t'" . DB_TABLE_PREFIX . "_permissions');\n";
$myconf .= "define('NC_TABLE_USERS',\t'" . DB_TABLE_PREFIX . "_users');\n";
$myconf .= "define('NC_ID_LEN',\t\t'" . NC_ID_LEN . "');\n";
$myconf .= "\n// Configuration for web server delivering content\n";
$myconf .= "define('NC_PATH',\t'$ncpath');\n";
$myconf .= "define('NC_CORE_PATH',\t'$ncpath/nc-core');\n";
$myconf .= "define('NC_CSS_PATH',\t'$ncpath/nc-core/css');\n";
$myconf .= "define('NC_INCLUDES_PATH',\t'$ncpath/nc-core/includes');\n";
$myconf .= "define('NC_JS_PATH',\t'$ncpath/nc-core/js');\n";
$myconf .= "define('NC_PHP_PATH',\t'$ncpath/nc-core/php');\n";
$myconf .= "define('NC_DATA_PATH',\t'$ncpath/nc-data');\n";
$myconf .= "define('NC_UI_PATH',\t'$ncpath/nc-ui');\n";
$myconf .= "\n// Configuration for API\n";
$myconf .= "define('NC_API_PATH',\t'" . SERVER . "$ncpath/nc-api/nc-api.php');\n";
$myconf .= "define('NC_APP_ID',\t'" . NC_APP_ID . "');\n";
$myconf .= "define('NC_APP_KEY',\t'$ncappkey');\n";
$myconf .= "\n// Configuration for website\n";
$myconf .= "define('NC_SITE_NAME',\t'" . NC_SITE_NAME . "');\n";
$myconf .= "\n?>";
file_put_contents("../config/nc-config.php", $myconf);
echo "ok\n";



echo "\n";
?>


