<?php
/*
 * Page with network graph
 * 
 */

// get all the classes for the network
$nodeclasses = $NCapi->getNodeClasses($network);
$linkclasses = $NCapi->getLinkClasses($network);
$graphnodes = $NCapi->getAllNodes($network);
$graphlinks = $NCapi->getAllLinks($network);

echo ncScriptObject("nc.ontology.nodes", $nodeclasses);
echo ncScriptObject("nc.ontology.links", $linkclasses);
echo ncScriptObject("nc.graph.rawnodes", $graphnodes);
echo ncScriptObject("nc.graph.rawlinks", $graphlinks);
?>


<div class="row">
    <div id="nc-graph-toolbar" class="col-sm-12"></div>
</div>
<div class="row">
    <div id="nc-graph-svg-container" class="col-sm-8">                       
        <svg id="nc-graph-svg"></svg>                    
    </div>
    <div id="nc-graph-side" class="col-sm-4">        
        <?php include_once "ui-network-graph-element.php"; ?>
    </div>
</div>


<!--
<script>
    debugNodes = function() {
        $('#nc-debugging').html(JSON.stringify(nc.graph.rawnodes)+"<br/><br/>"+JSON.stringify(nc.graph.nodes));
    }
    debugLinks = function() {
        $('#nc-debugging').html(JSON.stringify(nc.graph.rawlinks)+"<br/><br/>"+JSON.stringify(nc.graph.links));
    }
    debugUser = function() {
        alert("curate: "+nc.curator+" edit: "+nc.editor+" comment: "+nc.commentator);
        alert("settings: "+JSON.stringify(nc.graph.settings));
    }
    debugOntologies = function() {
        $('#nc-debugging').html(JSON.stringify(nc.ontology.nodes)+"<br/><br/>"+JSON.stringify(nc.ontology.links));
    }
</script>
<div class="nc-mt-10">Debugging</div>    
<a onclick="javascript:debugNodes(); return false;">Show nodes </a>
<a onclick="javascript:debugLinks(); return false;">Show links </a>
<a onclick="javascript:debugUser(); return false">Show user data </a>
<a onclick="javascript:debugOntologies(); return false">Show ontologies </a>
<div id="nc-debugging">
-->

</div>