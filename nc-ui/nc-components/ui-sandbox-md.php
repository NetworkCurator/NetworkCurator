<?php

/*
 * Just the markdown-preview panels
 */
?>


<div class="row nc-mt-10">
    <hr/>
    <div class="col-sm-6">
        <label>Markdown</label> <span class="nc-aside"><?php echo $mdaside; ?></span>       
        <textarea id="nc-sandbox-md" class="form-control" rows="20"></textarea>                
    </div>        
    <div class="col-sm-6">
        <label>Result</label>
        <div id="nc-sandbox-preview"></div>
    </div>        
</div>
