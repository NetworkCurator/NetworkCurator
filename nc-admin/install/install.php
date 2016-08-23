<?php

// Installation script for a NetworkCurator database
//

echo "\n";
echo "NetworkCurator installation\n\n";
include "../../nc-core/php/nc-generic.php";

// load the settings (also local settings)
$localfile = "install-settings-local.php";
if (file_exists($localfile)) {
    include $localfile;
}
include "install-settings.php";


/* --------------------------------------------------------------------------
 * Prep - helper functions and helper variables
 * -------------------------------------------------------------------------- */

// Executes an sql query and report quick answer
function sqlreport($c, $s) {
    if (mysqli_query($c, $s)) {
        echo "\tok\n";
    } else {
        echo "\tError: " . mysqli_error($c) . "\n";
    }
}

// Helper definitions for sql columns
$vc10col = " VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$vc24col = " VARCHAR(24) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$vc32col = " VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$vc64col = " VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$vc128col = " VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$vc256col = " VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";
$textcol = " TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ";



/* --------------------------------------------------------------------------
 * Start installation
 * -------------------------------------------------------------------------- */

try {
    
    // Create connection to database
    $conn1 = mysqli_connect(SERVER, DB_ROOT, DB_ROOT_PASSWD);
    if (!$conn1) {
        throw new Exception("Connection failed: " . mysqli_error($conn1) . "\n");
    }

    echo "Dropping existing database:";
    $sql = "DROP DATABASE IF EXISTS " . DB_NAME . " ";
    sqlreport($conn1, $sql);
    echo "\n";

    echo "Creating database:";
    $sql = "CREATE DATABASE " . DB_NAME . " ";
    $sql .= "DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    sqlreport($conn1, $sql);

    echo "Configuring database admin user:";
    $sql = "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,CREATE ";
    $sql .= "TEMPORARY TABLES,DROP,INDEX,ALTER ON " . DB_NAME . ".* TO ";
    $sql .= DB_ADMIN . "@" . SERVER . " IDENTIFIED BY '" . DB_ADMIN_PASSWD . "';";
    sqlreport($conn1, $sql);

    $conn1->close();
    echo "\n";
    
} catch (Exception $e) {
    echo "DROP/CREATE operations failed: " . $e->getMessage();
    echo "\n\n";
}


/* --------------------------------------------------------------------------
 * Create tables
 * -------------------------------------------------------------------------- */

$conn2 = mysqli_connect(SERVER, DB_ADMIN, DB_ADMIN_PASSWD, DB_NAME);
if (!$conn2) {
    die("Connection failed: " . mysqli_error($conn2) . "\n");
}


echo "Dropping existing tables:";
$tp = "" . DB_TABLE_PREFIX . "_";
$alltabs = array("users", "networks", "permissions",
    "nodes", "links", "annotations", "annotation_log");
$sql = "DROP TABLE IF EXISTS " . $tp . implode(", " . $tp, $alltabs);
sqlreport($conn2, $sql);
echo "\n";


// -----------------------------------------------------------------------------
// The users table will hold information about registered users

$tabname = $tp . "users";
echo "Creating table $tabname:";
$sql = "CREATE TABLE $tabname (
  datetime DATETIME NOT NULL,
  user_id $vc32col,
  user_firstname $vc64col,
  user_middlename $vc64col,
  user_lastname $vc64col,
  user_email $vc256col,
  user_pwd $vc64col, 
  user_extpwd $vc64col,   
  user_status INT NOT NULL,
  PRIMARY KEY (`user_id`)
  ) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The permissions table will hold network and user permissions
// e.g. is a given user permitted to view/annotate a given network?

$tabname = $tp . "permissions";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (
  user_id $vc32col,
  network_id $vc32col,    
  permissions INT,
  UNIQUE KEY `user_network` (`user_id`, `network_id`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX network_id ON $tabname (network_id(8)); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The networks table will hold a list of all available networks

$tabname = $tp . "networks";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,    
  network_name $vc64col,  
  owner_id $vc32col,  
  PRIMARY KEY (`network_id`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX network_name_id ON $tabname (network_name, network_id); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The nodes table will hold all nodes across all networks

$tabname = $tp . "nodes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,
  node_id $vc32col,
  class_id $vc32col,
  node_name $vc64col,  
  node_value DOUBLE NOT NULL,
  node_valueunit $vc24col,
  node_score DOUBLE NOT NULL,
  node_status INT NOT NULL,
  PRIMARY KEY (`node_id`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX class_id ON $tabname (class_id(8)); ";
sqlreport($conn2, $sql);


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
  link_value DOUBLE NOT NULL,
  link_valueunit $vc24col,
  link_score DOUBLE NOT NULL,
  link_status INT NOT NULL,
  PRIMARY KEY (`link_id`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname (1/3): ";
$sql = "CREATE INDEX from_id ON $tabname (from_id(8)); ";
sqlreport($conn2, $sql);
echo "Creating indexes on $tabname (2/3): ";
$sql = "CREATE INDEX to_id ON $tabname (to_id(8)); ";
sqlreport($conn2, $sql);
echo "Creating indexes on $tabname (3/3): ";
$sql = "CREATE INDEX class_id ON $tabname (network_id, class_id(8)); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The classes table will hold ontologies for nodes and links

$tabname = $tp . "classes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  network_id $vc32col,
  class_id $vc32col,
  class_name $vc64col,
  parent_id $vc32col,
  connector TINYINT(1) NOT NULL,
  directional TINYINT(1) NOT NULL,
  class_score DOUBLE NOT NULL,
  class_status INT NOT NULL,
  PRIMARY KEY (`class_id`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX network_id ON $tabname (network_id(8), parent_id); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The annotations table will hold comments, subcomments for all components

$tabname = $tp . "annotations";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (  
  datetime DATETIME NOT NULL,
  anno_id $vc32col,
  network_id $vc32col,  
  user_id $vc32col,  
  root_id $vc32col,
  parent_id $vc32col,  
  anno_text $textcol,
  anno_depth INT NOT NULL,
  anno_value DOUBLE NOT NULL,
  anno_valueunit $vc24col,
  anno_score DOUBLE NOT NULL,
  anno_status INT NOT NULL  
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname (1/2): ";
$sql = "CREATE INDEX root_id ON $tabname (root_id); ";
sqlreport($conn2, $sql);
echo "Creating indexes on $tabname (2/2): ";
$sql = "CREATE INDEX anno_id ON $tabname (anno_id(8)); ";
sqlreport($conn2, $sql);



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
  filesize BIGINT,
  PRIMARY KEY (`filename`)
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX network_id ON $tabname (network_id(8)); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The activity table will hold a summary of changes to the network
// This will be viewable by all users

$tabname = $tp . "activity";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (
  datetime DATETIME NOT NULL,
  user_id $vc32col,
  network_id $vc32col,    
  action $vc64col,
  target_id $vc32col,  
  value $textcol
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname (1/2): ";
$sql = "CREATE INDEX datetime ON $tabname (datetime); ";
sqlreport($conn2, $sql);
echo "Creating indexes on $tabname (2/2): ";
$sql = "CREATE INDEX network_id ON $tabname (network_id(6), datetime); ";
sqlreport($conn2, $sql);


// -----------------------------------------------------------------------------
// The log table will hold activity at the site level
// This information will be visible in the db, not on the website

$tabname = $tp . "log";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE $tabname (
  datetime DATETIME NOT NULL,
  user_id $vc32col,
  user_ip $vc128col,
  controller $vc64col,
  action $vc64col,
  value $textcol 
) COLLATE utf8_unicode_ci";
sqlreport($conn2, $sql);

echo "Creating indexes on $tabname: ";
$sql = "CREATE INDEX user_id ON $tabname (user_id(8), datetime); ";
sqlreport($conn2, $sql);
echo "\n";



/* --------------------------------------------------------------------------
 * Create required users
 * -------------------------------------------------------------------------- */

echo "Creating users:";
$userstable = $tp . "users";
$adminpass = md5("admin123");
$adminextp = md5(makeRandomHexString(60));
$sql = "INSERT INTO $userstable 
            (datetime, user_id, user_pwd, user_extpwd, user_firstname, user_lastname,
            user_email, user_status) VALUES 
            (UTC_TIMESTAMP(), 'admin',  '$adminpass', '$adminextp', 'Administrator',
                '', '', 1) , 
            (UTC_TIMESTAMP(), 'guest',  '', 'guest', 'Guest', '', '', 1)";
sqlreport($conn2, $sql);



/* --------------------------------------------------------------------------
 * Finish up with activity log
 * -------------------------------------------------------------------------- */

echo "Logging installation (1/2):";
$logtable = $tp . "activity";
$sql = "INSERT INTO $logtable 
          (datetime, user_id, network_id, target_id, action, value) VALUES 
          (UTC_TIMESTAMP(), 'admin', '', '',
          'installed the NetworkCurator database', '')";
sqlreport($conn2, $sql);


echo "Logging installation (2/2):";
$logtable = $tp . "log";
$sql = "INSERT INTO $logtable 
          (datetime, user_id, action, value) VALUES 
          (UTC_TIMESTAMP(), 'admin', 'install', '')";
sqlreport($conn2, $sql);

$conn2->close();
echo "\n";



/* --------------------------------------------------------------------------
 * Create configuration files for the site
 * -------------------------------------------------------------------------- */

// find the path for the web server
$ncpath = NC_PATH;
$ncappkey = makeRandomHexString(32);

// Create a configuration file for the 
$myconf = "<?php\n";
$myconf .= "\n// Configuration for connecting to the database\n";
$myconf .= "define('NC_SERVER',\t\t'" . SERVER . "');\n";
$myconf .= "define('NC_DB_NAME',\t\t'" . DB_NAME . "');\n";
$myconf .= "define('NC_DB_ADMIN',\t\t'" . DB_ADMIN . "');\n";
$myconf .= "define('NC_DB_ADMIN_PASSWD',\t'" . DB_ADMIN_PASSWD . "');\n";
$myconf .= "\n// Configuration for accessing data tables\n";
$myconf .= "define('NC_TABLE_ACTIVITY',\t'" . DB_TABLE_PREFIX . "_activity');\n";
$myconf .= "define('NC_TABLE_ANNO',\t\t'" . DB_TABLE_PREFIX . "_annotations');\n";
$myconf .= "define('NC_TABLE_CLASSES',\t\t'" . DB_TABLE_PREFIX . "_classes');\n";
$myconf .= "define('NC_TABLE_LINKS',\t'" . DB_TABLE_PREFIX . "_links');\n";
$myconf .= "define('NC_TABLE_LOG',\t\t'" . DB_TABLE_PREFIX . "_log');\n";
$myconf .= "define('NC_TABLE_NETWORKS',\t'" . DB_TABLE_PREFIX . "_networks');\n";
$myconf .= "define('NC_TABLE_NODES',\t'" . DB_TABLE_PREFIX . "_nodes');\n";
$myconf .= "define('NC_TABLE_PERMISSIONS',\t'" . DB_TABLE_PREFIX . "_permissions');\n";
$myconf .= "define('NC_TABLE_USERS',\t'" . DB_TABLE_PREFIX . "_users');\n";
$myconf .= "define('NC_ID_LEN',\t\t'" . NC_ID_LEN . "_users');\n";
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

echo "\n";
?>


