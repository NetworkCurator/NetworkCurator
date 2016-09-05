<?php
/**
 * Forms to create a new node or a new link
 * 
 */
?>

<div class="nc-new-nodelink" style="display: block;">
    <h3>Create a new node</h1> 
        <form role="form" id="" onsubmit="ncSendCreateNode(); return false;">
            <div id="ncfg-nodename" class="form-group">
                <label>Node name:</label>        
                <input type="text" class="form-control" 
                       placeholder="Node name">                     
            </div>    
            <div id="ncfg-nodetitle" class="form-group">
                <label>Node title:</label>        
                <input type="text" class="form-control" 
                       placeholder="Node title">                     
            </div> 
            <div id="ncfg-nodeclass" class="form-group">
                <label>Node class:</label> 
                <div class="input-group-btn">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                        <span class="pull-left nc-classname-span">[None]</span><span class="pull-right caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a href="#">[Action]</a></li>                        
                    </ul>    
                </div>
            </div>  
            <button type="submit" class="btn btn-success submit">Create</button>                
        </form>
</div>


<div class="nc-new-nodelink" style="display: block;">
    <h3>Create a new link</h1> 
        <form role="form" id="" onsubmit="ncSendCreateLink(); return false;">
            <div id="ncfg-linkname" class="form-group">
                <label>Link name:</label>        
                <input type="text" class="form-control" id="nc-linkname" 
                       placeholder="Link name">                         
            </div>    
            <div id="ncfg-linktitle" class="form-group">
                <label>Link title:</label>        
                <input type="text" class="form-control" id="nc-linktitle" 
                       placeholder="Link title">                 
            </div>     
            <div id="ncfg-linkclass" class="form-group">
                <label>Link class:</label> 
                <div class="input-group-btn">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                        <span class="pull-left nc-classname-span">[None]</span><span class="pull-right caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a href="#">[None]</a></li>                        
                    </ul>    
                </div>
            </div>  
            <button type="submit" class="btn btn-success submit">Create</button>                
        </form>
</div>
