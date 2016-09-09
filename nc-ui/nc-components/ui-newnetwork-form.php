<?php
/*
 * A network registration form
 * 
 */
?>

<div class="row">
    <div class="col-sm-5">
        <h1>Create a new network</h1> 
        <form role="form" onsubmit="nc.admin.createNetwork('fg-nname', 'fg-ntitle', 'fg-ndesc'); return false;">
            <div id="fg-nname" class="form-group">
                <label>Network name:</label>        
                <input type="text" class="form-control" placeholder="Network name">         
                <p class="help-block">This should be a short label without spaces, e.g.
                    'my-test'. The name will appear in urls in lowercase letters.</p>        
            </div>    
            <div id="fg-ntitle" class="form-group">
                <label>Network title:</label>        
                <input type="text" class="form-control" placeholder="Network title">         
                <p class="help-block">This should be a short running title, e.g.
                    'My test network'.</p>        
            </div>    
            <div id="fg-ndesc" class="form-group">
                <label>Network description:</label> 
                <textarea class="form-control" rows="4"></textarea>        
                <p class="help-block">This should be a concise description of the
                    network (max 250 characters).</p>        
            </div>        
            <button type="submit" class="btn btn-success submit">Create</button>                   
        </form>
    </div>
</div>
