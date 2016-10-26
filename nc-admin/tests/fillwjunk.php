<?php

// Installation script for a NetworkCurator database
//

echo "\n";
echo "NetworkCurator -- fill a table with junk\n\n";
include_once "../config/nc-config-local.php";
include_once "../../nc-api/helpers/nc-db.php";
include_once "../../nc-api/helpers/nc-generic.php";
include_once "../../nc-api/helpers/GeneralApiCaller.php";

$db = connectDB(NC_DB_SERVER, NC_DB_NAME, NC_DB_ADMIN, NC_DB_ADMIN_PASSWD);


/* --------------------------------------------------------------------------
 * Create junk data in ontology table
 * -------------------------------------------------------------------------- */


// Creating users here - exceptionally "manually" through a direct table insert
// On the web app, all other users should be inserted via the NCUsers class
echo "\nCreating junk:\n\n";
$sqlbase = "INSERT INTO nc_classes 
            (network_id, class_id, parent_id, connector, directional, class_score, class_status) 
            VALUES ";
$t0 = microtime(true);
$db->beginTransaction();

for ($i = 0; $i < 200; $i++) {        
    $junkarray = [];
    for ($j = 0; $j < 1000; $j++) {
        $t1 = makeRandomHexString(14);
        $t2 = makeRandomHexString(14);
        $junkarray[] .= "('$t1', '$t2', '', '0', '0', '0', 1)";
    }    
    $sql = $sqlbase . implode(", ", $junkarray);
    //echo $sql."\n";
    $t1 = microtime(true);
    $db->prepare($sql)->execute();
    $t2 = microtime(true);
    $tdiff = round(($t2-$t1)*1000, 2);
    echo $i."\t $tdiff \n";    
}
$db->commit();
            
$t9 = microtime(true);
$tdiff = round(($t9-$t0)*1000, 2);
echo "\nTotal: $tdiff \n";
        