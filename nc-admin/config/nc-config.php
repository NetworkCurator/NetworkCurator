<?php

// Configuration for connecting to the database
define('NC_DB_SERVER',		'localhost');
define('NC_DB_NAME',		'networkcurator');
define('NC_DB_ADMIN',		'nc_admin1');
define('NC_DB_ADMIN_PASSWD',	'pizzaLunch3Saturday');

// Configuration for accessing data tables
define('NC_TABLE_ACTIVITY',	'nc_activity');
define('NC_TABLE_ANNOTEXT',	'nc_anno_text');
define('NC_TABLE_ANNONUM',	'nc_anno_numeric');
define('NC_TABLE_CLASSES',	'nc_classes');
define('NC_TABLE_FILES',	'nc_datafiles');
define('NC_TABLE_LINKS',	'nc_links');
define('NC_TABLE_LOG',		'nc_log');
define('NC_TABLE_NETWORKS',	'nc_networks');
define('NC_TABLE_NODES',	'nc_nodes');
define('NC_TABLE_PERMISSIONS',	'nc_permissions');
define('NC_TABLE_USERS',	'nc_users');
define('NC_ID_LEN',		'6');

// Configuration for web server delivering content
define('NC_PATH',	'/NetworkCurator');
define('NC_CORE_PATH',	'/NetworkCurator/nc-core');
define('NC_CSS_PATH',	'/NetworkCurator/nc-core/css');
define('NC_INCLUDES_PATH',	'/NetworkCurator/nc-core/includes');
define('NC_JS_PATH',	'/NetworkCurator/nc-core/js');
define('NC_PHP_PATH',	'/NetworkCurator/nc-core/php');
define('NC_DATA_PATH',	'/NetworkCurator/nc-data');
define('NC_UI_PATH',	'/NetworkCurator/nc-ui');

// Configuration for API
define('NC_API_PATH',	'localhost/NetworkCurator/nc-api/nc-api.php');
define('NC_APP_ID',	'myncname');
define('NC_APP_KEY',	'0e19d233fccd48fa1ea52f7831894aed');

// Configuration for website
define('NC_SITE_NAME',	'NetworkCurator');

?>