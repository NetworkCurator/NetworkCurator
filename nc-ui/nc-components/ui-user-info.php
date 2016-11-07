<?php
/*
 * User account settings
 */

$targetid = $uid;
if (isset($_REQUEST['user'])) {
    $targetid = $_REQUEST['user'];
}

// fetch user account information
$uinfo = $NCapi->fetchUserInfo($targetid);
?>


<div class="row">
    <div class="col-sm-5">
        <h1>User information: <?php echo $uid; ?></h1> 
        <form role="form" onsubmit="nc.admin.updateUserInfo('fg-first','fg-middle', 'fg-last', 'fg-email', 'fg-pwd', 'fg-newpwd', 'fg-newpwd2'); return false;">
            <div id="fg-first" class="form-group">
                <label>First name:</label>        
                <input type="text" class="form-control" 
                       value="<?php echo $uinfo['user_firstname']; ?>">         
            </div>
            <div id="fg-middle" class="form-group">
                <label>Middle name (or initials):</label>
                <input type="text" class="form-control" 
                        value="<?php echo $uinfo['user_middlename']; ?>">   
            </div>
            <div id="fg-last" class="form-group">
                <label>Last name:</label>
                <input type="text" class="form-control" 
                        value="<?php echo $uinfo['user_lastname']; ?>">         
            </div>            
            <div id="fg-email" class="form-group">
                <label>Email address:</label>
                <input type="email" class="form-control" 
                       value="<?php echo $uinfo['user_email']; ?>">
            </div>
            <div id="fg-pwd" class="form-group">
                <label>*Password:</label>
                <input type="password" class="form-control" placeholder="Password">
            </div>    
            <div id="fg-newpwd" class="form-group">
                <label>New password:</label>
                <input type="password" class="form-control" placeholder="New password">
            </div>    
            
            <div id="fg-newpwd2" class="form-group">
                <label>Repeat new password:</label>
                <input type="password" class="form-control" placeholder="Confirm new password">
            </div>    
            <button type="submit" class="btn btn-success submit">Submit</button>  
            <div id="nc-update-response"></div>
        </form>
        
    </div>
    
    <div class="col-sm-4 nc-mt-20">
        <div class="nc-tips">
            <h4>Tips</h4>        
            <p>Use this form to update your name, email address, or change your password.</p>
            <p>The password field is required for all operations. </p>            
            <p>The 'new password' fields are required if you want to change your password; otherwise leave these blank.</p>            
        </div>        
    </div>
</div>
