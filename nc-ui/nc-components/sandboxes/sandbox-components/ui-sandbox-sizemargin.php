<?php
/*
 * form-groups used on sandbox pages
 * 
 * size and margin
 */

if (!isset($defaults)) {
    $defaults = [200, 200, 40, 20, 40,60];    
}
?>

<div class="form-group" val="size">                
    <label class="col-sm-2 control-label">Size (W, H)</label>                
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[0]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[1]; ?>">                        
    </div>
</div>
<div class="form-group" val="margin">                
    <label class="col-sm-2 control-label">Margin (T, R, B, L)</label>                
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[2]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[3]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[4]; ?>">                        
    </div>
    <div class="col-sm-1">
        <input type="text" class="form-control" value="<?php echo $defaults[5]; ?>">                        
    </div>
</div>