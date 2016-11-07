<?php
/*
 * A user registration form
 * 
 */
?>

<div class="row">
    <div class="col-sm-5">
        <h1>Register a new user</h1> 
        <form role="form" onsubmit="nc.admin.createUser('fg-first','fg-middle', 'fg-last', 'fg-id', 'fg-email', 'fg-pwd', 'fg-pwd2'); return false;">
            <div id="fg-first" class="form-group">
                <label>First name:</label>        
                <input type="text" class="form-control" placeholder="First name">         
            </div>
            <div id="fg-middle" class="form-group">
                <label>Middle name (or initials):</label>
                <input type="text" class="form-control" placeholder="Middle name">
            </div>
            <div id="fg-last" class="form-group">
                <label>Last name:</label>
                <input type="text" class="form-control" placeholder="Last name">
            </div>
            <div id="fg-id" class="form-group">
                <label>User id:</label>
                <input type="text" class="form-control" placeholder="User id">
            </div>
            <div id="fg-email" class="form-group">
                <label>Email address:</label>
                <input type="email" class="form-control" placeholder="Email address">
            </div>
            <div id="fg-pwd" class="form-group">
                <label>Password:</label>
                <input type="password" class="form-control" placeholder="Password">
            </div>    
            <div id="fg-pwd2" class="form-group">
                <label>Password:</label>
                <input type="password" class="form-control" placeholder="Confirm password">
            </div>    
            <button type="submit" class="btn btn-success submit">Submit</button>            
        </form>
        
    </div>

    <?php include_once "ui-user-list.php"; ?>    
</div>
