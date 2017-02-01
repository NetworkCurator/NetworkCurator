<?php

// This file contains settings using during the NetworkCurator installation
// Connection to SQL server
if (!defined("DB_SERVER")) {
    define("DB_SERVER", "localhost");
}


// Root user and password with full access
//
// This user must be created in the database server prior to installation
// e.g. in phpmyadmin on the "Privileges" page
//
// !! Change password in local copy !! 
// !! Create a file install-settings-local.php and add similar defs with new passwords
//
if (!defined("DB_ROOT")) {
    define("DB_ROOT", "nc_admin0");
}
if (!defined("DB_ROOT_PASSWD")) {
    define("DB_ROOT_PASSWD", "yellowflowersontheroadside");
}


// Name of new database
if (!defined("DB_NAME")) {
    define("DB_NAME", "networkcurator");
}

// name and password for database administrator
// This user must be created in the database prior to installation
//
// !! Change password in local copy !!
//
if (!defined("DB_ADMIN")) {
    define("DB_ADMIN", "nc_admin1");
}
if (!defined("DB_ADMIN_PASSWD")) {
    define("DB_ADMIN_PASSWD", "blueskieswithcloudstoday");
}


// prefix for tables
if (!defined("DB_TABLE_PREFIX")) {
    define("DB_TABLE_PREFIX", "nc");
}


// settings for web server - api
if (!defined("SERVER")) {
    define("SERVER", "localhost");
}

// settings for the web server - position of NetworkCurator on a webpage
if (!defined("NC_PATH")) {
    define("NC_PATH", "/NetworkCurator");
}


// name of NetworkCurator instance (api id)
if (!defined("NC_APP_ID")) {
    define("NC_APP_ID", "myncname");
}


// name of website name, e.g. NetworkCurator
if (!defined("NC_SITE_NAME")) {
    define("NC_SITE_NAME", "NetworkCurator");
}


// domain name for website, e.g. networkcurator.org
if (!defined("NC_SITE_DOMAIN")) {
    define("NC_SITE_DOMAIN", "networkcurator.org");
}


// URL for website, e.g. https://www.networkcurator.org
if (!defined("NC_SITE_URL")) {
    define("NC_SITE_URL", "https://www.networkcurator.org");    
}


// initial password for site admin
if (!defined("NC_SITE_ADMIN_PASSWORD")) {
    define("NC_SITE_ADMIN_PASSWORD", "admin123");
}


// length of random strings in ids
if (!defined("NC_ID_LEN")) {
    define("NC_ID_LEN", 8);
}
?>
