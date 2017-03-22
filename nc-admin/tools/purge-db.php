<?php
/*
 * Command line tool.
 * Purges all the contents of the db (very destructive!)
 * 
 */

// load the settings (also local settings)
$localfile = "../install/install-settings-local.php";
if (file_exists($localfile)) {
    include $localfile;
}
include "../install/install-settings.php";
include "../../nc-api/helpers/nc-generic.php";


// print a usage message if called without any arguments
if (count($argv)<3) {
    echo "USAGE: php purge-db.php DB_SERVER DB_NAME\n";
    exit();
}

// extract target network name from first argument (after purge-network.php)
$dbserver = $argv[1];
$dbname = $argv[2];

echo "NetworkCurator - purging db $dbserver $dbname:\n\n";



/* --------------------------------------------------------------------------
 * Drop tables
 * -------------------------------------------------------------------------- */

$db = new PDO('mysql:host=' . $dbserver . ';dbname=' . $dbname . ';charset=utf8mb4', DB_ADMIN, DB_ADMIN_PASSWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

echo "Dropping existing tables:";
$tp = "" . DB_TABLE_PREFIX . "_";
$alltabs = array("activity", "anno_numeric", "anno_text", "classes", "datafiles",
    "users", "networks", "permissions", "log",
    "nodes", "links", "annotations", "annotation_log");
$sql = "DROP TABLE IF EXISTS " . $tp . implode(", " . $tp, $alltabs);
ncQueryAndReport($db, $sql);


?>