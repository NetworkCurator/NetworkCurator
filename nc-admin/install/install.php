<?php

// Installation script for a NetworkCurator database
//

echo "\n";
echo "NetworkCurator installation\n\n";
include_once "../../nc-api/helpers/nc-generic.php";

// load the settings (also local settings)
$localfile = "install-settings-local.php";
if (file_exists($localfile)) {
    include_once $localfile;
}
include_once "install-settings.php";


/* --------------------------------------------------------------------------
 * Prep - helper variables
 * -------------------------------------------------------------------------- */

// Helper definitions for sql columns
$charset = "CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''";
$vc24col = " VARCHAR(24) $charset";
$vc32col = " VARCHAR(32) $charset";
$vc64col = " VARCHAR(64) $charset";
$vc128col = " VARCHAR(128) $charset";
$vc256col = " VARCHAR(256) $charset";
// the textcol cannot have a default value on some mysql installations?
$textcol = " MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
$statuscol = " TINYINT NOT NULL DEFAULT 1";
$dblcol = " DOUBLE NOT NULL DEFAULT 0.0";
$datecol = " DATETIME NOT NULL";

$engine = " ENGINE = InnoDB ";


/* --------------------------------------------------------------------------
 * Start installation
 * -------------------------------------------------------------------------- */

$db = new PDO('mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME .
        ';charset=utf8mb4', DB_ADMIN, DB_ADMIN_PASSWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$tp = "" . DB_TABLE_PREFIX . "_";


// -----------------------------------------------------------------------------
// The users table will hold information about registered users

$tabname = $tp . "users";
echo "Creating table $tabname:";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (
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
  ) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The permissions table will hold network and user permissions
// e.g. is a given user permitted to view/annotate a given network?

$tabname = $tp . "permissions";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (
  user_id $vc32col,
  network_id $vc32col,    
  permissions INT NOT NULL DEFAULT 0,
  UNIQUE KEY user_network (user_id, network_id),
  KEY network_id (network_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The networks table will hold a list of all available networks

$tabname = $tp . "networks";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  network_id $vc32col,       
  owner_id $vc32col,  
  PRIMARY KEY (network_id)  
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The nodes table will hold all nodes across all networks

$tabname = $tp . "nodes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  network_id $vc32col,
  node_id $vc32col,
  class_id $vc32col,    
  node_status $statuscol,
  PRIMARY KEY (node_id),
  KEY network_id (network_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


$tabname = $tp."positions";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (
    network_id $vc32col,
    node_id $vc32col,
    pos_x $dblcol,
    pos_y $dblcol,
    PRIMARY KEY (node_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The links table will hold all links across all networks

$tabname = $tp . "links";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  network_id $vc32col,
  link_id $vc32col,
  source_id $vc32col,
  target_id $vc32col,
  class_id $vc32col,  
  link_status $statuscol,
  PRIMARY KEY (link_id),
  KEY source_id (source_id),
  KEY target_id (target_id),
  KEY network_id (network_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The classes table will hold ontologies for nodes and links
// add constraints on link from/to classes?

$tabname = $tp . "classes";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  network_id $vc32col,
  class_id $vc32col,       
  parent_id $vc32col,
  connector TINYINT NOT NULL DEFAULT 0,
  directional TINYINT NOT NULL DEFAULT 0,
  class_score $dblcol,
  class_status $statuscol,
  PRIMARY KEY (class_id)    
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The annotations table will hold comments, subcomments for all components

$tabname = $tp . "anno_text";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (    
  datetime DATETIME NOT NULL,
  modified DATETIME,
  anno_id $vc32col,
  anno_type INT NOT NULL DEFAULT 0,
  owner_id $vc32col,
  user_id $vc32col,  
  network_id $vc32col,  
  root_id $vc32col,
  parent_id $vc32col,  
  anno_text $textcol,  
  anno_status $statuscol,  
  KEY anno_type (network_id, anno_type), 
  KEY anno_id (anno_id),
  KEY root_id (network_id, root_id)  
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


$tabname = $tp . "anno_numeric";
echo "Creating table $tabname:";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  datetime DATETIME NOT NULL,
  modified DATETIME,
  anno_id $vc32col,
  anno_type INT NOT NULL DEFAULT 0,
  owner_id $vc32col,
  user_id $vc32col,  
  network_id $vc32col,  
  root_id $vc32col,
  parent_id $vc32col,      
  anno_value $dblcol,
  anno_valueunit $vc24col,  
  anno_status $statuscol,
  KEY anno_type (network_id, anno_type),
  KEY anno_id (anno_id),
  KEY root_id (network_id, root_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The datafiles table will hold metadata for uploaded files

$tabname = $tp . "datafiles";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (  
  datetime DATETIME NOT NULL,
  file_id $vc32col,  
  user_id $vc32col,  
  network_id $vc32col,    
  file_name $vc256col,  
  file_type $vc24col,
  file_desc $vc256col,
  file_size BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (file_id),
  KEY network_id (network_id)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The activity table will hold a summary of changes to the network
// This will be viewable by all users

$tabname = $tp . "activity";
echo "Creating table $tabname: ";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (
  datetime $datecol,
  user_id $vc32col,
  network_id $vc32col,    
  action $vc64col,
  target_name $vc128col,  
  value $textcol, 
  KEY network_id (network_id, datetime)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


// -----------------------------------------------------------------------------
// The log table will hold activity at the site level
// This information will be visible in the db, not on the website

$tabname = $tp . "log";
echo "Creating table $tabname:\t";
$sql = "CREATE TABLE IF NOT EXISTS $tabname (
  datetime $datecol,
  user_id $vc32col,
  user_ip $vc128col,
  controller $vc64col,
  action $vc64col,
  value $textcol,
  KEY user_id (user_id, datetime)
) $engine COLLATE utf8_unicode_ci";
ncQueryAndReport($db, $sql);


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
ncQueryAndReport($db, $sql);


// Create an icon for the admin
$img = imagecreate(48, 48);
$imgbg = imagecolorallocate($img, 0, 0, 0);
$imgfile = "../../nc-data/users/admin.png";
imagepng($img, $imgfile);

// Create an icon for the guest
$img = imagecreate(48, 48);
$imgbg = imagecolorallocate($img, 127, 127, 127);
$imgfile = "../../nc-data/users/guest.png";
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
ncQueryAndReport($db, $sql);


echo "Logging installation (2/2):";
$logtable = $tp . "log";
$sql = "INSERT INTO $logtable 
          (datetime, user_id, action, value) VALUES 
          (UTC_TIMESTAMP(), 'admin', 'install', '')";
ncQueryAndReport($db, $sql);

// close the connection
$db = null;
echo "\n";


/* --------------------------------------------------------------------------
 * Create configuration files for the site
 * -------------------------------------------------------------------------- */

echo "Writing site config file:\t";
include_once "configure.php";

?>


