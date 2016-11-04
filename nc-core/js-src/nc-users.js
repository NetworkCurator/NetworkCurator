/* 
 * nc-users.js
 * 
 * Function of user log-in / log out.
 * 
 */


// create a namespace within nc for user functions
if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.users = {};




/* ====================================================================================
* Section about logging in
* ==================================================================================== */

/**
 * Invoke to attempt user log-ing
 * Extracts values from a form and seds request to the server
 * 
 *
 * @param fgid - id of formgroup containing user id
 * @param fgpwd - id of formgroup containing password
 * @param fgremember - id of formgroup containing remember checkbox
 */
nc.users.sendLogin = function(fgid, fgpwd, fgremember) {
    
    $('#'+fgid+',#'+fgpwd).removeClass('has-warning has-error');
           
    // basic checks on the form  
    if (nc.utils.checkFormInput(fgid, "user id", 1) 
        + nc.utils.checkFormInput(fgpwd, "password", 3)<2) return 0;    
    
    // post the login request 
    $.post(nc.api, 
    {
        controller: "NCUsers", 
        action: "verify",        
        target_id: $('#'+fgid+' input').val(),
        target_password: $('#'+fgpwd+' input').val(),
        remember: $('#'+fgremember+' input').is(':checked')
    }, function(data) {         
        nc.utils.alert(data);                
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==false) {
                $('#fg-userid,#fg-password').addClass('has-error has-feedback');                
                $('#fg-userid label').html("Please verify the user id is correct:");
                $('#fg-password label').html("Please verify the password is correct:");
            } else {
                window.location.replace("?page=front");
            }
        }                             
    }
    );    
    
    return 0;
}


/**
 * This function is here for symmetry with sendLogin. But the log-out work
 * is actually done in a server-side page.
 */
nc.users.sendLogout = function() {
    window.location.replace("?page=logout");
}



/* ====================================================================================
* Section about user permissions
* ==================================================================================== */


/*
* Invoked when curator presses "Lookup" to get user information
* 
*/
nc.users.lookup = function() {
        
    // find the value associated with the selected permission level        
    var targetid = $("#nc-form-permissions input").val();
                
    // check if name is well-formed              
    if (nc.utils.checkString(targetid, 1)==0) { 
        nc.msg('Hey!', 'Invalid user id');  
        return false;
    }
        
    var btn = $("#nc-permissions-lookup");    
    btn.removeClass("btn-success").addClass("btn-warning disabled").html("Checking");
    
    // api checks if user exists and indeed has no access        
    $.post(nc.api, 
    {
        controller: "NCUsers", 
        action: "queryPermissions", 
        network: nc.network,
        target: targetid        
    }, function(data) {        
        nc.utils.alert(data);        
        data = JSON.parse(data);
        btn.html('Lookup').removeClass('btn-warning disabled').addClass('btn-success');                    
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false) {              
                nc.msg('Error', data['errormsg']);                
            } else {                
                if (data['data']==0) {                    
                    // the target user exists and indeed cannot view the network
                    // offer to grant permissions
                    $('#nc-grantconfirm-user').html(targetid);                    
                    $('#nc-grantconfirm-network').html(nc.network);                    
                    $('#nc-grantconfirm-modal').modal('show');                    
                } else {
                    nc.msg('Response', 'User already has permissions');                    
                }                
            }
        }      
    });
    return false;
}


/*
* Invoked when admin pressed "Update" next to a network permission widget
*/
nc.users.updatePermissions = function(targetid) {

    // check for self-adjustment
    if (targetid==nc.userid) {
        var warnm  = "<p>You are about to update your own permission level.</p>";
        warnm += "<p>If you set your permission level below curator level, you will no longer be able to make adjustments on this page.</p>";        
        $('#nc-danger-header').html("Permissions");
        $('#nc-danger-body').html(warnm);
        $('#nc-danger-modal').modal('show');
        $('#nc-danger-modal button[val="nc-ok"]').click(function() {
            nc.users.confirmUpdatePermissions(targetid); 
            $(this).off("click");
        });    
    } else {        
        nc.users.confirmUpdatePermissions(targetid);          
    }    
            
}


/**
 * performs an update of permissions (after the action has been validated/confirmed)
 */
nc.users.confirmUpdatePermissions = function(targetid) {
    
    var nowform = $('form.nc-form-permissions[val="'+targetid+'"]');
    var nowval = nowform.find("label.active").find("input:radio").val();    
    // find the update button for this user
    var btn = nowform.find('button')    
    btn.addClass('btn-warning disabled').html('Updating');    
        
    // call the update permissions api
    nc.users.updatePermissionsGeneric(targetid, nowval, 
        function (data) {            
            data = JSON.parse(data);
            btn.removeClass('btn-warning btn-success').html('Done').addClass('btn-default');        
            setTimeout(function(){
                btn.html('Update').removeClass('btn-default disabled').addClass('btn-success');            
            }, nc.ui.timeout); 
            if (nc.utils.checkAPIresult(data) && data['success']==false) {                                 
                nc.msg('Error', data['errormsg']);                                 
                return;
            }                        
            if (nowval==0 && targetid!=="guest") {                
                // if setting user to 0, remove the form element from the page
                $('.nc-form-permissions[val="'+targetid+'"]').fadeOut(nc.ui.speed, function() {                
                    $(this).remove();
                }); 
            }
        });  
}

/**
* Invoked when curator confirms to grant privileges to a user
*/
nc.users.grantView = function() {
    var targetid = $('#nc-form-permissions input').val();       
    nc.users.updatePermissionsGeneric(targetid, 1, 
        function myfun(data) {                
            nc.utils.alert(data); 
            data = JSON.parse(data);
            // clear the text box and add new row to the widget
            $('#nc-form-permissions input').val('');                     
            var new_item = $(nc.ui.PermissionsWidget(data['data'])).hide();
            $('#nc-permissions-users').append(new_item);
            new_item.show(nc.ui.speed);        
        });
    return false;        
}


/**
 * Sends an api request to update permissions on a network
 * 
 * @param targetid - target user id
 * @param perm - integer, new permission level
 * @param f - function invoked to process api response
 * 
 */
nc.users.updatePermissionsGeneric = function(targetid, perm, f) {
    $.post(nc.api, 
    {
        controller: "NCUsers", 
        action: "updatePermissions", 
        network_name: nc.network,
        target_id: targetid,
        permissions: perm
    }, f );   
}
