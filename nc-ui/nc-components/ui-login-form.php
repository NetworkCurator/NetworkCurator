<?php
/*
 * A simple log-in form
 * 
 */
?>

<h1>Login</h1>
<form role="form" onsubmit="ncSendLogin(); return false;">    
    <div id="ncfg-userid" class="form-group">
        <label for="email">User id:</label>
        <input type="text" class="form-control" id="nc-userid" placeholder="User id">
    </div>    
    <div id="ncfg-password" class="form-group">
        <label for="pwd">Password:</label>
        <input type="password" class="form-control" id="nc-pwd" placeholder="Password">
    </div>
    <div class="checkbox form-group">
        <label><input type="checkbox" id="nc-remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-success submit">Log in</button>    
</form>