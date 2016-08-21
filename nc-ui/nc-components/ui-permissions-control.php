<?php
/*
 * Form group for network configuration page: 
 * Shows a permission update widget
 * 
 */
?>


<?php
$targetid = $permissions['Userid'];
?>
<form class="form-inline nc-form-permissions" onsubmit="return false;">  
    <div class="form-group" id="<?php echo "ncfg-permissions-$targetid"; ?>" style="width:100%">        
        <label class="col-form-label nc-fg-name">
            <?php
            echo $targetid;
            if ($targetid != "guest")
                echo " (" . $permissions['Fullname'] . ")";
            ?>
        </label>             
        <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-default nc-btn-permissions <?php echo $permissions['None']; ?>">
                <input type="radio" value="0" autocomplete="off" <?php echo $permissions['None']; ?>>None
            </label>
            <label class="btn btn-default nc-btn-permissions <?php echo $permissions['View']; ?>">
                <input type="radio" value="1" autocomplete="off" <?php echo $permissions['View']; ?>>View
            </label>
            <label class="btn btn-default nc-btn-permissions <?php echo $permissions['Comment']; ?>">
                <input type="radio" value="2" autocomplete="off" <?php echo $permissions['Comment']; ?>>Comment
            </label>
            <label class="btn btn-default nc-btn-permissions <?php echo $permissions['Edit']; ?>">
                <input type="radio" value="3" autocomplete="off" disabled="<?php echo $permissions['Edit']; ?>">Edit
            </label>
            <label class="btn btn-default nc-btn-permissions <?php echo $permissions['Curate']; ?>">
                <input type="radio" value="4" autocomplete="off" disabled="<?php echo $permissions['Curate']; ?>">Curate
            </label>
        </div>
        <?php
        $onclick = "javascript:ncUpdatePermissions('" . $network . "','" . $permissions['Userid'] . "'); return false;";
        ?>
        <button class="btn btn-success" onclick="<?php echo $onclick; ?>">Update</button>        
    </div>
</form>    