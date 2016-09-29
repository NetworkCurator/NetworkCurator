<?php
/*
 * Page with data import/export functions
 * 
 * Assumes some variables are already set by index.php
 * $uid, $network, $NCApi, $upermissions
 * 
 */

// get all the classes for the network
?>

<div class="row">
    <div class="col-sm-5">
        <h3 class="nc-mt-10">Export data</h3>            
        <p>Download data for this network. (To do, not working yet)</p>
        <form role="form" id="nc-export-form" onsubmit="nc.data.exportData(); return false;">            
            <div id="fg-export-summary" class="checkbox form-group">
                <label><input type="checkbox" checked disabled>Network Data</label>
            </div>            
            <div id="fg-export-comments" class="checkbox form-group">
                <label><input type="checkbox">Comments</label>
            </div>
            <div id="fg-export-old" class="checkbox form-group">
                <label><input type="checkbox">Annotation history</label>
            </div>
            <button type="submit" class="btn btn-success submit">Download</button>            
        </form>



        <hr/>
        <h3 class="nc-mt-15">Import data</h3>        
        <form role="form" id="nc-import-form" onsubmit="nc.data.importData('fg-importfile', 'fg-importmsg'); return false;">
            <div id="nc-data-import"></div>                
            <div id="fg-importfile" class="form-group">
                <label>Data file:</label>
                <input type="file">                
            </div>
            <div id="fg-importmsg" class="form-group">
                <label>Description (required):</label>        
                <input type="text" class="form-control" placeholder="Description">         
            </div>
            <button type="submit" class="btn btn-success submit">Submit</button>            
        </form>
        <div id="nc-import-response"></div>


    </div>
    <div class="col-sm-3"></div>

    <div class="col-sm-4 nc-mt-10">
        <div class="nc-tips nc-curator">
            <h4>Tips</h4>        
            <p>Use the <b>download</b> form to make a local copy of the complete or partial network data.</p>
            <p>Use the <b>Upload</b> form to submit ontology, node, or link definitions in bulk.</p>
        </div>
    </div>
</div>


<div class="modal fade vertical-alignment-helper" id="nc-data-modal" role="dialog">
    <div class="modal-dialog vertical-align-center">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Please confirm</h4>
            </div>
            <div class="modal-body">
                <p>
                    Are you sure you want to <span id="nc-dataconfirm-action">upload</span> 
                    file <b><span id="nc-dataconfirm-file" val=""></span></b>?                    
                </p>
                <p><b>This is important!</b></p>
                <p val="upload">
                    Data from your file will be sent and saved on the server. 
                    Definitions in the file that match existing network objects will be used
                    to update the existing information. Definitions that do not match 
                    existing objects will be used to create new ontology classes, nodes, or links.                    
                </p>
                <p val="download">
                    To do: Comment on download.
                </p>                
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" data-dismiss="modal" val="nc-confirm">OK</button>
                <button class="btn btn-warning" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
