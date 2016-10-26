<?php
/*
 * Entry point page for an individual network
 * 
 * Assumes some variables are already set by index.php
 * $uid, $network, $NCApi
 * 
 */

// if user does not have at least view permissions, redirect
if (!$upermissions || $upermissions < 1) {
    header("Refresh: 0; ?page=front");
    exit();
}

// get network title and description
$nettitle = $NCapi->getNetworkTitle($network);

// start making an object with all the markdown components, and all the comments
$netmd = [];
$netcomments = [];

// For toolbar - get what aspect of the network to view (summary, graph, log, etc)
$view = 'summary';
if (isset($_REQUEST['view'])) {
    $view = strtolower($_REQUEST['view']);
}
if (!in_array($view, array('graph', 'log', 'data', 'permissions', 'ontology'))) {
    $view = 'summary';
}
// helper object for writing href in toolbar
$coreurl = "?network=$network&view=";


// identify what graph object to look up
if (isset($_REQUEST['object'])) {        
    $view = 'object';    
} 


?>

<nav class="navbar navbar-default nc-navbar navbar-static-top nc-navbar2">
    <div class="container">
        <div class="navbar-collapse"> 
            <ul class="nav navbar-nav">  
                <li class='<?php if ($view == 'summary') echo 'active'; ?>'>
                    <a href='<?php echo $coreurl . 'summary'; ?>'>Summary</a>
                </li>
                <li class='<?php if ($view == 'ontology') echo 'active'; ?>'>
                    <a href='<?php echo $coreurl . 'ontology'; ?>'>Ontology</a>
                </li>                                
                <li class='<?php if ($view == 'graph') echo 'active'; ?>'>
                    <a href='<?php echo $coreurl . 'graph'; ?>'>Graph</a>
                </li>                
                <li class='<?php if ($view == 'data') echo 'active'; ?>'>
                    <a href='<?php echo $coreurl . 'data'; ?>'>Data</a>
                </li>                
                <li class='<?php if ($view == 'log') echo 'active'; ?>'>
                    <a href='<?php echo $coreurl . 'log'; ?>'>Log</a>
                </li>      
                <li id="nc-permissions-tab" class='nc-curator<?php if ($view == 'permissions') echo ' active'; ?>'
                    style="display: none">
                    <a href='<?php echo $coreurl . 'permissions'; ?>'>Permissions</a>
                </li>
            </ul>
            <ul class="nav navbar-nav pull-right nc-nav-icon">                
                <li class="nc-curator" style="display: none">
                    <a role="button" id="nc-curation-lock" class="nc-looking">                        
                        <span class="glyphicon glyphicon-pencil"></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>


<?php
// after the menu, include the contents specific to each view
include_once "nc-ui/nc-components/ui-network-$view.php";

// write out the markdown and comments into the page
echo "<script>nc.networktitle='$nettitle';</script>";
echo ncScriptObject("nc.md", $netmd);
echo ncScriptObject("nc.comments", $netcomments);
?>

