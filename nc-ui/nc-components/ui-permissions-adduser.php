<?php
/*
 * Form group for the network configuraiton page: 
 * Shows a widget to add user to the list
 * 
 */
?>


<form class="form-inline nc-form-permissions" id="nc-form-permissions" onsubmit="return false;">  
    <div class="form-group" id="ncfg-permissions-adduser" style="width:100%">        
        <label class="col-form-label nc-fg-name">Look up another user:</label>     
        <input type="text" class="form-control" placeholder="User id">                 
        <button id="nc-permissions-lookup" class="btn btn-success" 
                onclick="nc.users.lookup(); return false;">Lookup</button>                
    </div>
</form>

<div class="modal fade vertical-alignment-helper" id="nc-grantconfirm-modal" role="dialog">
    <div class="modal-dialog vertical-align-center">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Please confirm</h4>
            </div>
            <div class="modal-body">
                <p>
                    Are you sure you want to give user 
                    <b><span id="nc-grantconfirm-user"></span></b>
                    view access to network <b><span id="nc-grantconfirm-network"></span></b>?
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" data-dismiss="modal" onclick="nc.users.grantView()">OK</button>
                <button class="btn btn-danger" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>


