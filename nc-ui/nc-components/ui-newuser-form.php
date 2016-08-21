<?php
/*
 * A user registration form
 * 
 */
?>

<div class="row">
    <div class="col-sm-5 offset-sm-2">
        <h1>Register a new user</h1> 
        <form role="form" id="ncf-newuser" onsubmit="ncSendCreateUser(); return false;">
            <div id="ncfg-firstname" class="form-group">
                <label for="nc-firstname">First name:</label>        
                <input type="text" class="form-control" id="nc-firstname" placeholder="First name">         
            </div>
            <div id="ncfg-middlename" class="form-group">
                <label for="nc-middlename">Middle name (or initials):</label>
                <input type="text" class="form-control" id="nc-middlename" placeholder="Middle name">
            </div>
            <div id="ncfg-lastname" class="form-group">
                <label for="nc-lastname">Last name:</label>
                <input type="text" class="form-control" id="nc-lastname" placeholder="Last name">
            </div>
            <div id="ncfg-userid" class="form-group">
                <label for="nc-userid">User id:</label>
                <input type="text" class="form-control" id="nc-userid" placeholder="User id">
            </div>
            <div id="ncfg-email" class="form-group">
                <label for="nc-email">Email address:</label>
                <input type="email" class="form-control" id="nc-email" placeholder="Email address">
            </div>
            <div id="ncfg-password" class="form-group">
                <label for="nc-pwd">Password:</label>
                <input type="password" class="form-control" id="nc-pwd" placeholder="Password">
            </div>    
            <div id="ncfg-password2" class="form-group">
                <label for="nc-pwd">Password:</label>
                <input type="password" class="form-control" id="nc-pwd2" placeholder="Confirm password">
            </div>    
            <button type="submit" class="btn btn-success submit">Submit</button>
            <div id="ncf-result"></div>
        </form>
        
    </div>
</div>
