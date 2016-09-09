<?php
/*
 * Page with network graph
 * 
 */
include_once "nc-core/php/nc-helper-classes.php";

// get all the classes for the network
$nodeclasses = $NCapi->getNodeClasses($network);
$linkclasses = $NCapi->getLinkClasses($network);

// convert from complex structure to simpler arrays, write it out in json
echo "<script>nc_node_classes= ".json_encode(getFlatClassList($nodeclasses))."; 
    nc_link_classes= ".json_encode(getFlatClassList($linkclasses))."; </script>";
?>


<div class="row">
    <div id="nc-graph" class="col-sm-8">
        <div id="nc-graph-toolbar"></div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div id="nc-graph-svg-container">
            <svg id="nc-graph-svg"></svg>            
        </div>
    </div>
    <div class="col-sm-4">
        <?php include_once "ui-network-graph-newelement.php"; ?>
    </div>
</div>

