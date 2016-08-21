/* 
 * networkcurator.js
 * 
 * Javascript functions for NetworkCurator
 * 
 * 
 */


/**
 * A combination used for debugging. 
 */
ncdebug = false;
function ncAlert(x) {
    if (ncdebug) alert(x);
}


/* ==========================================================================
 * Constants
 * ========================================================================== */

// used for toggling buttons with feedback
nc_timeout = 4000;


/* ==========================================================================
 * Actions on page load
 * ========================================================================== */

/**
 * This fixes a "bug" in bootstrap that allows radio buttons to accept clicks
 * and change active state even if they are set to disabled
 */
$(document).ready(
    function () {
        $('.btn-group .disabled').click(function(event) {
            event.stopPropagation();
        });   
    });


/* ==========================================================================
 * Generic functions 
 * ========================================================================== */

/**
 * Check if a string is composed of a proper combination of characters
 * 
 * x - an input string
 * type - integer code. 
 *   use 0 for id-like strings (strictly alphanumeric, minimum length)
 *   use 1 for lenient id-like strings (alphanumeric, with _-)
 *   use 2 for name-like strings (spaces, dashes, and apostrophe allowed)
 *   use 3 for passwords (special chars allowed, minimum length 6)
 *   
 *   use negative values to skip the length requirement
 */
function ncCheckString(x, type) {
        
    // ncAlert("ncCheckString with "+x+" "+type);
    // define characters that are allowed in the string x
    var ok = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if (type==1) {
        ok = ok + "_-";
    } else if (type==2) {
        ok = ok + " '-";
    } else if (type==3) {
        ok = ok + "_-$-.+!*'(),";
    }
        
    // perform length and composition checks    
    var xlen = x.length;
    if (type>=0) {
        if (xlen<2) return 0;    
        if (type==3 && xlen<8) return 0;            
    }
    for (var i=0; i<xlen; i++) {        
        if (ok.indexOf(x[i])<0) return 0; 
    }
    
    return 1;	
}

/**
 * Checks types within input elements in a form group div
 * 
 * nn - string, function looks for elements ncfg-nn
 * nnlong - longer text, used to update a label in the form group
 * type - integer, used in conjuction with ncCheckString
 * 
 * returns 1 is the input value matches the type
 * returns 0 if there is a problem (also updates the ui)
 * 
 */
function ncCheckFormInput(nn, nnlong, type) {
    var checkelement = '#ncfg-'+nn+' input'
    if ($(document).find(checkelement).length == 0) { 
        var checkelement = '#ncfg-'+nn+' textarea';
    }
    if (ncCheckString($(checkelement).val(), type)==0) {    
        $('#ncfg-'+nn).addClass('has-warning');
        $('#ncfg-'+nn+' label').html("Please enter a (valid) "+nnlong+":");
        return 0;
    }   
    return 1;
}


/**
 * Tests if email is well-formed
 */ 
function ncCheckFormEmail(nn) {
    var ee = $('#nc-email').val();
    var la = ee.indexOf('@');
    if (la== -1 || ee.indexOf('.',la)<2) {
        $('#ncfg-'+nn).addClass('has-warning');
        $('#ncfg-'+nn+' label').html("Please enter a (valid) email:");
        return 0;		
    }
    return 1;
}


/**
 * checks that an object x is consistent with an API return 
 */
function ncCheckAPIresult(x) {
    if ("success" in x && ("data" in x || "errormsg" in x)) {
        return true;
    } else {
        if (debug) {
            alert("Something wrong in API result: "+ x.toString());
        }
        return false;
    }
}


/* ==========================================================================
 * Section on processing the login form
 * ========================================================================== */

/*
 * Invoked from user login page. 
 * Extracts values from the form and sends a request to the server.
 * 
 */
function ncSendLogin() {
    
    $('#ncfg-userid,#ncfg-password').removeClass('has-warning has-error');
       
    // basic checks on the form  
    if (ncCheckFormInput("userid", "user id", 1) 
        + ncCheckFormInput("password", "password", 3)<2) return 0;    
            
    // post the login request 
    $.post(nc_api, 
    {
        controller: "NCUsers", 
        action: "verify", 
        target_id: $('#nc-userid').val(),
        target_password: $('#nc-pwd').val(),
        remember: $('#nc-remember').is(':checked')
    }, function(data) {         
        ncAlert(data);        
        data = $.parseJSON(data);
        if (ncCheckAPIresult(data)) {
            if (data['success']==false) {
                $('#ncfg-userid,#ncfg-password').addClass('has-error has-feedback');                
                $('#ncfg-userid label').html("Please verify the username is correct:");
                $('#ncfg-password label').html("Please verify the password is correct:");
            } else {
                window.location.replace("?page=splash");
            }
        }                                
    }
    );    
    
    return 0;
}


/* ==========================================================================
 * Section on processing the network creation form
 * ========================================================================== */

/*
 * Invoked from the network creation page (admin)
 * Extracts values from the form and sends a network creation request to server
 *  
 */ 
function ncSendCreateNetwork() {

    $('#ncfg-networkname,#ncfg-networktitle,#ncfg-networkdesc').removeClass('has-warning has-error');
    // basic checks on the network name text box    
    if (ncCheckFormInput("networkname", "network name", 1)+
        ncCheckFormInput("networktitle", "network title", 2) +
        ncCheckFormInput("networkdesc", "network description", 2) < 3) return 0;
        
    // post the registration request 
    $.post(nc_api, 
    {
        controller: "NCNetworks", 
        action: "createNewNetwork", 
        network_name: $('#nc-networkname').val(),
        network_title: $('#nc-networktitle').val(),
        network_desc: $('#nc-networkdesc').val()
    }, function(data, status) {          
        ncAlert(data);        
        data = $.parseJSON(data);
        if (ncCheckAPIresult(data)) {
            if (data['success']==false || data['data']==false) {
                $('#ncfg-networkname').addClass('has-error has-feedback');                
                $('#ncfg-networkname label').html("Please choose another network name:");                
            } else if (data['success']==true && data['data']==true) {                    
                $('#ncfg-networkname').addClass('has-success has-feedback');                                
                $('#ncfg-networkname input').attr("readonly", true);                                   
                $('#ncf-newnetwork button').hide();
                $('#ncf-result').html('<b>Success!</b>');                
            }
        }                        
    }
    );    
     
    return 1;
}

/* ==========================================================================
 * Section on processing user accounts
 * ========================================================================== */

/**
 * Invoked from admin page when creating a new user.
 * Extracts values from the form and sends a request to the server.
 * 
 */
function ncSendCreateUser() {
    $('#ncf-newuser formgroup').removeClass('has-warning has-error');
         
    // basic checks on the for text boxes     
    if (ncCheckFormInput("firstname", "first name", 1) 
        + ncCheckFormInput("middlename", "middle name", -1)
        + ncCheckFormInput("lastname", "last name", 1)
        + ncCheckFormInput("userid", "user id", 1)
        + ncCheckFormInput("password", "password", 1)
        + ncCheckFormInput("password2", "password", 1)
        + ncCheckFormEmail("email", "email") < 7) return 0;            
    if ($('#pwd').val() != $('#pwd2').val()) {
        $('#ncfg-password2').addClass('has-warning');
        $('#ncfg-password2 label').html("Please re-confirm the password:");
        return 0;
    }
                
    $.post(nc_api, 
    {
        controller: "NCUsers", 
        action: "createNewUser", 
        firstname: $('#nc-firstname').val(),
        middlename: $('#nc-middlename').val(),
        lastname: $('#nc-lastname').val(),
        target_id: $('#nc-userid').val(),
        email: $('#nc-email').val(),
        target_password: $('#nc-pwd').val()
    }, function(data, status) {          
        ncAlert(data);        
        data = $.parseJSON(data);
        if (ncCheckAPIresult(data)) {
            if (data['success']==false || data['data']==false) {
                $('#ncfg-userid').addClass('has-error has-feedback');                
                $('#ncfg-userid label').html("Please choose another user id:");                
            } else if (data['success']==true && data['data']==true) {                 
                $('#ncf-newuser').addClass('has-success has-feedback');                                
                $('#ncf-newuser .form-group input').attr("readonly", true);                   
                $('#ncf-newuser button').hide();
                $('#ncf-result').html('<b>Success!</b>');                                 
            }
        }                        
    });    
    
    return 1;
}


/* ==========================================================================
 * Section on processing user permissions
 * ========================================================================== */

/*
 * Invoked when admin pressed "Update" next to a network permission widget
 */
function ncUpdatePermissions(netname, uid) {
            
    // find the value associated with the selected permission level
    var nowval = $("#ncfg-permissions-"+uid).find("label.active").find("input:radio").val();
    
    // find the update button for this user
    var btn = $("#ncfg-permissions-"+uid+' button')
    btn.addClass('btn-warning disabled');
    btn.html('Please wait');

    // call the update permissions api
    ncUpdatePermissionsGeneric(uid, netname, nowval, 
        function (data) {
            ncAlert(data);                            
            btn.removeClass('btn-warning btn-success').html('Done').addClass('btn-default');        
            setTimeout(function(){
                btn.html('Update').removeClass('btn-default disabled').addClass('btn-success');            
            }, nc_timeout); 
        });            
}


/*
 * Invoked when admin pressed "Lookup" to get user information
 * 
 * The button serves
 */
function ncLookupUser(netname) {
        
    // find the value associated with the selected permission level
    var idfield = $("#nc-permissions-adduserid");
    idfield.prop("disabled", true);
    var uid = idfield.val();

    var btn = $("#nc-permissions-lookup");    
    var cancelbtn = $("#nc-permissions-cancel"); 
    var btnlab = btn.text();
        
    var granttext = "Grant view access";
    // if trying to grant view access, just call the update permissions api
    if (btnlab==granttext) {        
        ncUpdatePermissionsGeneric(uid, netname, 1, 
            function myfun(data) {
                ncAlert(data);  
                $("#nc-permissions-userid").html("");
                btn.removeClass('btn-warning btn-success').addClass("btn-default");
                btn.html('Refresh the page to see changes, or add another');
                setTimeout(function(){
                    btn.html('Lookup').removeClass('btn-default disabled').addClass('btn-success');                                
                }, nc_timeout*2);
                cancelbtn.addClass("hidden");
                idfield.prop("disabled", false);
            });
        return;        
    } 

    // if reached here, still trying to lookup the user, check if is is well-formed              
    if (ncCheckString(uid, 1)==0) {            
        btn.removeClass('btn-success').addClass('btn-default disabled').html('Invalid userid');        
        setTimeout(function(){
            btn.html('Lookup').removeClass('btn-default disabled').addClass('btn-success');            
            idfield.prop("disabled", false);
        }, nc_timeout/2);    
        return;
    }
        
    btn.removeClass("btn-success").addClass("btn-warning disabled").html("Checking");
    
    // api checks if user exists and indeed has no access        
    $.post(nc_api, 
    {
        controller: "NCUsers", 
        action: "queryPermissions", 
        network_name: netname,
        target_id: uid        
    }, function(data) {
        ncAlert(data);        
        data = $.parseJSON(data);
        btn.removeClass("btn-warning").addClass('btn-default');
        if (ncCheckAPIresult(data)) {            
            if (data['success']==false) {                
                btn.html(data['errormsg']);                
                setTimeout(function(){
                    btn.html('Lookup').removeClass('btn-default disabled').addClass('btn-success');            
                    idfield.prop("disabled", false);
                }, nc_timeout); 
            } else {
                if (data['data']==0) {
                    // the target user exists and indeed cannot view the network
                    btn.html(granttext).removeClass('btn-default disabled').addClass('btn-success');
                    cancelbtn.removeClass('hidden');                    
                } else {
                    // the target user already can view the network
                    btn.html("User already has permissions");                        
                    setTimeout(function(){
                        btn.html('Lookup').removeClass('btn-default disabled').addClass('btn-success');            
                        idfield.prop("disabled", false);
                    }, nc_timeout); 
                }                
            }
        }                
    });
}


/**
 * Invoked when admin tries to grant privileges to user, but decides to cancel
 */
function ncGrantViewCancel() {
    $("#nc-permissions-adduserid").prop("disabled", false);    
    $("#nc-permissions-lookup").html("Lookup");    
    $("#nc-permissions-cancel").addClass("hidden"); 
}


/**
 * Sends an api request to update permissions on a network
 * 
 * uid - target user id
 * netname - name of network
 * perm - integer, new permission level
 * outfun - function invoked to process api response
 * 
 */
function ncUpdatePermissionsGeneric(uid, netname, perm, outfun) {
    $.post(nc_api, 
    {
        controller: "NCUsers", 
        action: "updatePermissions", 
        network: netname,
        target_id: uid,
        permissions: perm
    }, outfun );   
}


/* ==========================================================================
 * misc code, leftovers
 * ========================================================================== */

if (4>5) {
    $('.btn').click(function(e) {
        e.preventDefault();
        $(this).addClass('active');
    })
}