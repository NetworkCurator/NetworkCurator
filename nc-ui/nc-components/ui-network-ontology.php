<?php
/*
 * Page showing/editing ontology for the network
 * 
 * Assumes some variables are already set by index.php
 * $uid, $network, $NCApi, $upermissions
 * 
 */

// get all the classes for the network
$nodeclasses = $NCapi->getNodeClasses($network);
$linkclasses = $NCapi->getLinkClasses($network);


?>

<div class="row">
    <div class="col-sm-8">
        <h3 class="nc-mt-10">Nodes</h3>    
        <div id="nc-ontology-nodes" class="nc-ontology-tree">
        </div>
        <h3 class="nc-mt-15">Links</h3>
        <div id="nc-ontology-links" class="nc-ontology-tree">
        </div>
    
<?php
echo "<script>";
echo "nc.ontology.nodes=" . json_encode($nodeclasses) . ";";
echo "nc.ontology.links=" . json_encode($linkclasses) . ";";
echo "</script>";
?>                            


    </div>

    <div class="col-sm-4 nc-mt-10">
        <div class="nc-tips <?php
if (!$iscurator) {
    echo "hidden";
}
?>">
            <h4>Tips</h4>        
            <p>Use the <b>Create new class</b> form to create a new type of node or link.</p>
            <p>Click the <b>Move</b> button and drag to build a hierarchy of classes. 
                Then use the <b>Edit/Update</b> button to register the changes in the database.</p>
            <p>Use the <b>Edit/Update</b> button to change the name associated with a node/link class.</p>        
            <p>Click the <b>Remove</b> button to deprecate a given class.</p>            
        </div>
    </div>
</div>
