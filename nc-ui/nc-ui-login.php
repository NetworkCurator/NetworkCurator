<?php
/*
 * Page for user login
 * 
 */

// if the user is already logged-in, that's not good (can't log in twice)
if ($_SESSION['uid'] !== "guest") {
    header("Refresh: 0, url=?page=front");
}
?>

<div class="row" style="margin-top:20px">    
    <div class="col-sm-4">        
        <?php
        include_once "nc-components/ui-login-form.php";
        ?>
    </div>
</div>

