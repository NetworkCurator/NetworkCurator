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

<!-- <h1 class="nc-mt-5">Configuration for network <?php echo $network; ?></h1> -->

<div class="row">
    <div class="col-sm-12">
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
    </div>
</div>

