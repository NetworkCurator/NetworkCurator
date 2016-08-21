<?php
/*
 * Form group for the network configuraiton page: 
 * Shows a widget to add user to the list
 * 
 */
?>


<form class="form-inline nc-form-permissions" onsubmit="return false;">  
    <div class="form-group" id="ncfg-permissions-adduser" style="width:100%">        
        <label class="col-form-label nc-fg-name">Look up another user:</label>     
        <input type="text" class="form-control" id="nc-permissions-adduserid" placeholder="ids">                 
        <button id="nc-permissions-lookup" class="btn btn-success" 
                onclick="ncLookupUser('<?php echo $network; ?>'); return false;">Lookup</button>                
        <button id="nc-permissions-cancel" class="btn btn-danger hidden" onclick="ncGrantViewCancel(); return false;">Cancel</button>        
    </div>
</form>
