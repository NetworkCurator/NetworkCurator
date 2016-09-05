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

        <script>  
<?php
echo "var network_name='$network'; ";
echo "var nc_node_classes=" . json_encode($nodeclasses) . ";";
echo "var nc_link_classes=" . json_encode($linkclasses) . ";";
?>                            
    $(document).ready(
    function () {                                               
        $('#nc-ontology-nodes').html(ncuiClassTreeWidget('<?php echo $network ?>', nc_node_classes, false, <?php echo $iscurator; ?>));                
        $('#nc-ontology-links').html(ncuiClassTreeWidget('<?php echo $network; ?>', nc_link_classes, true, <?php echo $iscurator; ?>));                          
    });            
        </script>

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
            <p>Click and hold the <b>Remove</b> button to deprecate a given class.</p>
            <p><b>Reload</b> the page to abandon changes and start again.</p>        
        </div>
    </div>
</div>
