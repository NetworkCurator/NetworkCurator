<?php
/*
 * A network registration form
 * 
 */
?>

<div class="row">
    <div class="col-sm-5">
        <h1>Create a new network</h1> 
        <form role="form" id="ncf-newnetwork" onsubmit="ncSendCreateNetwork(); return false;">
            <div id="ncfg-networkname" class="form-group">
                <label for="nc-networkname">Network name:</label>        
                <input type="text" class="form-control" id="nc-networkname" 
                       placeholder="Network name">         
                <p class="help-block">This should be a short label without spaces, e.g.
                    'my-test'. The name will appear in urls in lowercase letters.</p>        
            </div>    
            <div id="ncfg-networktitle" class="form-group">
                <label for="nc-networktitle">Network title:</label>        
                <input type="text" class="form-control" id="nc-networktitle" 
                       placeholder="Network title">         
                <p class="help-block">This should be a short running title, e.g.
                    'My test network'.</p>        
            </div>    
            <div id="ncfg-networkdesc" class="form-group">
                <label for="nc-networkdesc">Network description:</label> 
                <textarea class="form-control" rows="4" id="nc-networkdesc"></textarea>        
                <p class="help-block">This should be a concise description of the
                    network (max 250 characters).</p>        
            </div>        
            <button type="submit" class="btn btn-success submit">Create</button>
            <div id="ncf-result"></div>            
        </form>
    </div>
</div>