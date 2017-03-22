<?php

// Script for updating NetworkCurator config files 
// 
// This creates file nc-admin/config/nc-config-local.php
// 
// The script is run during installation. 
// 
// It can also be run post-installation to change the site configuration.
// This can be useful to update some constants in nc-admin/config
// without re-running the full installation. (A full installation drops existing tables!)
//

include_once "../../nc-api/helpers/nc-generic.php";

// load the settings (also local settings)
$localfile = "install-settings-local.php";
if (file_exists($localfile)) {
    include_once $localfile;
}
include_once "install-settings.php";



/* --------------------------------------------------------------------------
 * Create configuration files for the site
 * -------------------------------------------------------------------------- */

// find the path for the web server
$ncpath = NC_PATH;
$ncappkey = makeRandomHexString(32);

// Create a configuration file for the 
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
$myconf .= "define('NC_TABLE_FILES',\t'" . DB_TABLE_PREFIX . "_datafiles');\n";
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
$myconf .= "define('NC_SITE_URL',\t'" . NC_SITE_URL . "');\n";
$myconf .= "define('NC_SITE_DOMAIN',\t'" . NC_SITE_DOMAIN . "');\n";
$myconf .= "\n?>";
file_put_contents("../config/nc-config-local.php", $myconf);
echo "Done\n";



?>
