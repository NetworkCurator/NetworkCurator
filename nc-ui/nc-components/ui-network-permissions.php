<?php
/*
 * Page that allows setting configurations (permissions) for a given network
 * e.g. giving users rights to view, comment, edit.
 * 
 */

if ($_SESSION['uid'] !== "admin") {
    if (isset($upermissions)) {
        if ($upermissions < 4) {
            header("Refresh: 0; ?page=front");
            exit();
        }
    } else {
        header("Refresh: 0; ?page=front");
        exit();
    }
}

// get all users who have permissions on the dataset
$ispublic = $NCapi->isNetworkPublic($network);
$netusers = $NCapi->listNetworkUsers($network);

// turn the ispublic boolean into an array like netusers
$guestuser = array('user_firstname' => 'guest', 'user_middlename' => '',
    'user_lastname' => '', 'user_id' => 'guest');
if ($ispublic === false) {
    $guestuser['permissions'] = 0;
} else {
    $guestuser['permissions'] = 1;
}
$guestusers = array('guest' => $guestuser);
?>

<div class="row">
    <div class="col-sm-8">
                
        <h3 class="nc-mt-15">Public access</h3>    
        <div id="nc-permissions-guest">
        </div>
        <h3 class="nc-mt-15">User permissions</h3>
        <div id="nc-permissions-users">
        </div>
                        
        <script>  
        <?php
        echo "nc.permissions.guest=" . json_encode($guestusers) . ";";
        echo "nc.permissions.users=" . json_encode($netusers) . ";";
        ?>                                            
        </script>

        <h3 class="nc-mt-15">Add users to the network</h3>
        <?php
        // show a button to add users to the network
        include "ui-permissions-adduser.php";
        ?>
        
        <?php         
        if ($upermissions == NC_PERM_SUPER) {
            echo '<h3 class="nc-mt-15">Administration</h3><div id="nc-administration"></div>';
        }        
        ?>
    </div>
    
    <div class="col-sm-4 nc-mt-10">
        <div class="nc-tips nc-curator">
            <h4>Tips</h4>        
            <p>Use the <b>Lookup</b> form to grant users access to the network.</p>
            <p>Click the <b>permission levels</b> buttons to adjust the privileges of each user. 
                Then use the <b>Update</b> button to register the changes in the database.</p>
            <p>Permissions for the <b>guest</b> user determine whether the network is viewable by 
            non-registered users.</p>                    
        </div>
    </div>
</div>

