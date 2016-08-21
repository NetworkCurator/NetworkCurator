<?php
/*
 * Entry point page for an individual network
 * 
 * Assumes some variables are already set by index.php
 * $uid, $network, $NCApi
 * 
 */


// get the permission level for this user
try {
    $upermissions = $NCapi->queryPermissions($network);            
} catch (Exception $e) {
    // this case handles situations where the network code is not a valid network name
    $upermissions = 0;    
}

// if user does not have at least view permissions, redirect
if (!$upermissions || $upermissions < 1) {
    header("Refresh: 0; ?page=splash");
    exit();
}

// get network title and description
$netmeta = $NCapi->getNetworkMetadata($network);

?>


<h1 class="nc-mt-10"><?php echo $netmeta['title'];?></h1>
<div id="network-abstract"><?php echo $netmeta['description']; ?></div>

<h3 class="nc-mt-10">Curators</h3>
<?php echo ui_listnames($netmeta['curators']); ?>

<h3 class="nc-mt-10">Authors</h3>
<?php echo ui_listnames($netmeta['authors']); ?>

<h3 class="nc-mt-10">Commentators</h3>
<?php echo ui_listnames($netmeta['commentators']); ?>




