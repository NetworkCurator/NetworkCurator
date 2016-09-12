<?php
/*
 * Page with network graph
 * 
 */
include_once "nc-core/php/nc-helper-classes.php";

// get all the classes for the network
$nodeclasses = $NCapi->getNodeClasses($network);
$linkclasses = $NCapi->getLinkClasses($network);
$graphnodes = $NCapi->getAllNodes($network);

echo ncScriptObject("nc.ontology.nodes", $nodeclasses);
echo ncScriptObject("nc.ontology.links", $linkclasses);
echo ncScriptObject("nc.graph.nodes", $graphnodes);

?>


<div class="row">
    <div id="nc-graph-toolbar" class="col-sm-8"></div>
</div>
<div class="row">
    <div id="nc-graph-svg-container" class="col-sm-8">        
        <svg id="nc-graph-svg"></svg>                    
    </div>
    <div class="col-sm-4">        
        <?php include_once "ui-network-graph-element.php"; ?>
    </div>
</div>



<script>
    debugNodes = function() {
        $('#nc-debugging').html(JSON.stringify(nc.graph.nodes));
    }
    debugLinks = function() {
        $('#nc-debugging').html(JSON.stringify(nc.graph.links));
    }
    </script>
<a onclick="javascript:debugNodes(); return false;">Show nodes</a>
<a onclick="javascript:debugLinks(); return false;">Show links</a>
<div id="nc-debugging">
    
</div>