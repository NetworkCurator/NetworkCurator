<?php
/*
 * A network registration form
 * 
 */
?>

<div class="row">
    <div class="col-sm-12">
        <h1>Create a new network</h1> 
    </div>

    <div class="col-sm-5">
        <h2>From scratch</h2>
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

    <div class="col-sm-5 pull-right">

        <h2>From file</h2>         
        <form role="form" id="nc-import-form" onsubmit="nc.admin.importNetwork('fg-importfile'); return false;">
            <div id="nc-data-import"></div>                
            <div id="fg-importfile" class="form-group">
                <label>Data file:</label>
                <input type="file"> 
                <p class="help-block">The data file must be in json format. See 
                    the documentation and the <a href="?page=sandbox&sandbox=preparenetwork">network prep sandbox</a> for 
                    expected file format.</p>        
            </div>            
            <button type="submit" class="btn btn-success submit">Create</button>            
        </form>
        <div id="nc-import-response"></div>
    </div>
</div>
