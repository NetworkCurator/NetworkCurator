<?php

// This file contains settings using during the NetworkCurator installation
// Connection to SQL server
define("SERVER", "localhost");


// Root user and password with full access
//
// This user must be created in the database server prior to installation
// e.g. in phpmyadmin on the "Privileges" page
//
// !! Change password in local copy !! 
//
define("DB_ROOT", "nc_admin0");
if (!defined("DB_ROOT_PASSWD")) {
    define("DB_ROOT_PASSWD", "yellowflowersontheroadside");
}


// Name of new database
define("DB_NAME", "networkcurator");


// name and password for database administrator
// This user must be created in the database prior to installation
//
// !! Change password in local copy !!
//
define("DB_ADMIN", "nc_admin1");
if (!defined("DB_ADMIN_PASSWD")) {
    define("DB_ADMIN_PASSWD", "blueskieswithcloudstoday");
}


// prefix for tables
define("DB_TABLE_PREFIX", "nc");


// settings for the web server - position of NetworkCurator on a webpage
define("NC_PATH", "/NetworkCurator");


// name of NetworkCurator instance (api id)
define("NC_APP_ID", "myncname");


// name of website name, e.g. NetworkCurator
define("NC_SITE_NAME", "NetworkCurator");


// length of random strings in ids
define("NC_ID_LEN", 9);
?>
