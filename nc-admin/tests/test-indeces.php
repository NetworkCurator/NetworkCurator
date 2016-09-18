<?php

echo "\n";
echo "Test of index behavior\n\n";
include "../../nc-api/helpers/nc-generic.php";
include "../../nc-api/helpers/nc-db.php";

// load the settings (also local settings)
$localfile = "../install/install-settings-local.php";
if (file_exists($localfile)) {
    include $localfile;
}
include "../install/install-settings.php";



$db = new PDO('mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_ADMIN, DB_ADMIN_PASSWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$sql = "DROP INDEX root_id ON nc_anno_text";
echo "A1";
$db->prepare($sql)->execute();
echo "A2";
try {
$db->prepare($sql)->execute();
} catch(Exception $ex) {
echo "twice not good ";    
}
echo "A3\n";

$sql = "CREATE INDEX root_id ON nc_anno_text (network_id, root_id)";
$db->prepare($sql)->execute();
echo "A4";
try {
    $db->prepare($sql)->execute();
} catch(Exception $ex) {
    echo "twice not good";
}

echo "A5";


?>
