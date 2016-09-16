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
echo ncScriptObject("nc.ontology.nodes", $nodeclasses);
echo ncScriptObject("nc.ontology.links", $linkclasses);
?>                            
    </div>

    <div class="col-sm-4 nc-mt-10">
        <div class="nc-tips nc-curator">
            <h4>Tips</h4>        
            <p>Use the <b>Create new class</b> form to create a new type of node or link.</p>
            <p>Click the <b>Move</b> button and drag to build a hierarchy of classes. 
                Then use the <b>Edit/Update</b> button to register the changes in the database.</p>
            <p>Use the <b>Edit/Update</b> button to change the name associated with a node/link class.</p>        
            <p>Click the <b>Remove</b> button to deprecate a given class.</p>            
        </div>
    </div>
</div>


<div class="modal fade vertical-alignment-helper" id="nc-deprecateconfirm-modal" role="dialog">
    <div class="modal-dialog vertical-align-center">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Please confirm</h4>
            </div>
            <div class="modal-body">
                <p>
                    Are you sure you want to <span id="nc-deprecateconfirm-action">deprecate</span> 
                    class <b><span id="nc-deprecateconfirm-class" val=""></span></b>?                    
                </p>
                <p><b>Heads up!</b></p>
                <p val="deprecate">
                    If the class has already been applied to a graph element, 
                    deprecating the class will also deprecate all those elements, but will not delete them. 
                    The class will remain visible on this page and can be re-activated later.
                </p>
                <p val="deprecate">
                    If the class has not been used yet, this action will remove it 
                    completely.                    
                </p>
                <p val="activate">
                    Activating the deprecated class will make it accessible again for use. 
                    Any graph elements using the class will remain 
                    deprecated until re-activated separately. 
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" data-dismiss="modal" val="confirm">OK</button>
                <button class="btn btn-warning" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
