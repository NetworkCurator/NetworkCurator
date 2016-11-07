<?php

/**
 * generates a table with all existing users
 * 
 */
$allusers = $NCapi->listUsers();

?>

<div class="col-sm-6 pull-right">
    <h2 class="nc-mt-20">Existing users</h2>
    TO DO: buttons to activate/inactivate user accounts
    <div id="nc-users-table">        
    </div>
</div>
<script>
nc.ui.createUsersTable(<?php echo json_encode($allusers); ?>, 'nc-users-table');
</script>



