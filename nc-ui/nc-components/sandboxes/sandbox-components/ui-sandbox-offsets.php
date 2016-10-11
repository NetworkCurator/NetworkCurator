<?php
/*
 * form-groups used in sandbox pages
 * 
 */

if (!isset($defaults)) {
    $defaults = ["-1.5em", "2.5em", "-2em"];    
}

?>

<div class="form-group" val="offset">                
    <label class="col-sm-2 control-label">Label Offset (T, X, Y)</label>                
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[0]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[1]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[2]; ?>">                        
    </div>                
</div>