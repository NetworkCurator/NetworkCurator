<?php
/*
 * Page that allows setting configurations (permissions) for a given network
 * e.g. 
 */

// get all users who have permissions on the dataset
$ispublic = $NCapi->isNetworkPublic($network);
$netusers = $NCapi->listNetworkUsers($network);

$blank = array('None' => '', 'View' => '', 'Comment' => '', 'Edit' => '', 'Curate' => '',
    'None.checked' => '', 'View.checked' => '', 'Comment.checked' => '',
    'Edit.checked' => '', 'Curate.checked' => '',
    'Fullname' => '');

//$output = shell_exec('php hello.php');
?>

<div class="row">    
    <div class="col-sm-12">
        <h1>Configuration for network: '<?php echo $network; ?>'</h1> 


    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <h3 class="nc-mt-15">Public access</h3>        
        <?php
        // create a permission control for the guest user
        $permissions = $blank;
        $permissions['Fullname'] = "guest";
        $permissions['Userid'] = "guest";
        if ($ispublic === false) {
            $permissions['None'] = "active";
        } else {
            $permissions['View'] = "active";
        }
        $permissions['Comment'] = "disabled";
        $permissions['Edit'] = "disabled";
        $permissions['Curate'] = "disabled";
        include "ui-permissions-control.php";
        ?>        
        <h3 class="nc-mt-15">User permissions</h3>
        <?php
        // create a permission control row for each user       
        for ($x = 0; $x < count($netusers); $x++) {
            $nowuser = $netusers[$x];
            $permissions = $blank;            
            $permissions['Fullname'] = ncFullname($nowuser);
            $permissions['Userid'] = $nowuser['user_id'];            
            switch ($nowuser['permissions']) {
                case 0;
                    $permissions['None'] = "active";
                    break;
                case 1;
                    $permissions['View'] = "active";
                    break;
                case 2;
                    $permissions['Comment'] = "active";
                    break;
                case 3;                    
                    $permissions['Edit'] = "active";
                    break;
                case 4;
                    $permissions['Curate'] = "active";
                    break;
            }
            include "ui-permissions-control.php";
        }               
        ?>
        
        <h3 class="nc-mt-15">Add users to the network</h3>
        <?php
        // show a button to add users to the network
        include "ui-permissions-adduser.php";
        ?>
    </div>
</div>