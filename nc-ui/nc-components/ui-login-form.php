<?php
/*
 * A simple log-in form
 * 
 */
?>

<h1>Login</h1>
<form role="form" onsubmit="nc.users.sendLogin('fg-userid', 'fg-password', 'fg-remember'); return false;">    
    <div id="fg-userid" class="form-group">
        <label for="email">User id:</label>
        <input type="text" class="form-control" placeholder="User id">
    </div>    
    <div id="fg-password" class="form-group">
        <label for="pwd">Password:</label>
        <input type="password" class="form-control" placeholder="Password">
    </div>
    <div id="fg-remember" class="checkbox form-group">
        <label><input type="checkbox">Remember me</label>
    </div>
    <button type="submit" class="btn btn-success submit">Log in</button>    
</form>