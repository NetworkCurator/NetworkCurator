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
    $upermissions = $NCapi->querySelfPermissions($network);
} catch (Exception $e) {
    // this case handles situations where the network code is not a valid network name
    $upermissions = 0;
}
//echo "uperm: $upermissions";

// if user does not have at least view permissions, redirect
if (!$upermissions || $upermissions < 1) {
    header("Refresh: 0; ?page=front");
    exit();
}

// get network title and description
$netmeta = $NCapi->getNetworkMetadata($network);
//print_r($netmeta);

// get what aspect of the network to view (summary, graph, log, etc)
$view = 'summary';
if (isset($_REQUEST['view'])) {
    $view = strtolower($_REQUEST['view']);
}
if ($view !== 'graph' && $view !== 'log'
        && $view !== 'permissions' && $view !== "ontology") {
    $view = 'summary';
}
?>


<?php
// some helper objects used to insert into the menu
$coreurl = "?page=network&network=$network&view=";
$ca = array('summary' => '', 'graph' => '', 'permissions' => '', 'ontology'=>'', 'log' => '');
$ca[$view] = "class='active'";
if ($upermissions < 4) {
    $ca['users'] = "class='hidden'";
    $ca['classes'] = "class='hidden'";
}
?>
<nav class="navbar navbar-default nc-navbar navbar-static-top navbar2">
    <div class="container">
        <div class="navbar-collapse"> 
            <ul class="nav navbar-nav">  
                <li class='<?php if ($view=='summary') echo 'active'; ?>'><a href='<?php echo $coreurl . 'summary'; ?>'>Summary</a></li>
                <li class='<?php if ($view=='graph') echo 'active'; ?>'><a href='<?php echo $coreurl . 'graph'; ?>'>Graph</a></li>                
                <li class='<?php if ($view=='ontology') echo 'active'; ?>'><a href='<?php echo $coreurl . 'ontology'; ?>'>Ontology</a></li>                
                <li class='<?php if ($view=='log') echo 'active'; ?>'><a href='<?php echo $coreurl . 'log'; ?>'>Log</a></li>      
                <li class='admin<?php if ($upermissions < 4) echo " hidden"; if ($view=='permissions') echo ' active';?>'>
                    <a href='<?php echo $coreurl . 'permissions'; ?>'>Permissions</a></li>
            </ul>
        </div>
    </div>
</nav>


<?php
// after the menu, include the contents specific
//echo "<br/>FFF";
include_once "nc-ui/nc-components/ui-network-$view.php";
?>



<script>
    $(document).ready(
    function () {           
        $('#nc-nav-network-title').html('<?php echo $netmeta['network_title'] ?>');
        $('body').addClass('body2');
    });
</script>







