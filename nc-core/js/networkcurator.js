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
        ncAlert("Something wrong in API result: "+ x.toString());        
        return false;
    }
}


/**
 * Show a message in a modal window
 */
function ncMsg(h, b) {
    $('#nc-msg-header').html(h);
    $('#nc-msg-body').html(b);
    $('#nc-msg-modal').modal('show');
}


/**
 * Disable clicks on disabled elements
 */
function ncDisabledClick(target) {    
    $(target+' .disabled').click(function(event) {
        event.stopPropagation();
    });   
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
                window.location.replace("?page=front");
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
        ncCheckFormInput("networktitle", "network title", 2) < 2) return 0;    
    var networkdesc = $("<div>").html($('#nc-networkdesc').val()).text();
    if (networkdesc.length>250) return 0;
       
    // post the registration request 
    $.post(nc_api, 
    {
        controller: "NCNetworks", 
        action: "createNewNetwork", 
        network_name: $('#nc-networkname').val(),
        network_title: $('#nc-networktitle').val(),
        network_desc: networkdesc
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
        target_firstname: $('#nc-firstname').val(),
        target_middlename: $('#nc-middlename').val(),
        target_lastname: $('#nc-lastname').val(),
        target_email: $('#nc-email').val(),
        target_id: $('#nc-userid').val(),        
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
            data = $.parseJSON(data);
            btn.removeClass('btn-warning btn-success').html('Done').addClass('btn-default');        
            setTimeout(function(){
                btn.html('Update').removeClass('btn-default disabled').addClass('btn-success');            
            }, nc_timeout); 
            if (ncCheckAPIresult(data) && data['success']==false) {                                 
                ncMsg('Error', data['errormsg']);                                 
                return;
            }                        
            if (nowval==0 && uid!=="guest") {                
                // if setting user to 0, remove the form element from the page
                var ncfp = $('#ncf-permissions-'+uid);
                ncfp.fadeOut('normal', function() { ncfp.remove() }); 
            }
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
    var uid = idfield.val();

    var btn = $("#nc-permissions-lookup");    
               
    // if reached here, still trying to lookup the user, check if is is well-formed              
    if (ncCheckString(uid, 1)==0) { 
        ncMsg('Hey!', 'Invalid user id');  
        return false;
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
        btn.html('Lookup').removeClass('btn-warning disabled').addClass('btn-success');                    
        if (ncCheckAPIresult(data)) {            
            if (data['success']==false) {              
                ncMsg('Error', data['errormsg']);                
            } else {                
                if (data['data']==0) {                    
                    // the target user exists and indeed cannot view the network
                    // offer to grant permissions
                    $('#nc-grantconfirm-user').html(uid);                    
                    $('#nc-grantconfirm-network').html(netname);                    
                    $('#nc-grantconfirm-modal').modal('show');                    
                } else {
                    ncMsg('Response', 'User already has permissions');                    
                }                
            }
        }
        idfield.prop("disabled", false);
    });
    return false;
}


/**
 * Invoked when admin confirms to grant privileges to a user
 */
function ncGrantViewOk() {
    var uid = $("#nc-grantconfirm-user").html();    
    var netname =$("#nc-grantconfirm-network").html();            
    ncUpdatePermissionsGeneric(uid, netname, 1, 
        function myfun(data) {                
            ncAlert(data); 
            data = $.parseJSON(data);
            // clear the text box
            $("#nc-permissions-userid").html("");         
            // create a new array to pass on to the widget builder
            var new_item = $(ncuiPermissionsWidget(netname, data['data'])).hide();
            $("#nc-permissions-users").append(new_item);
            new_item.show('normal');        
        });
    return false;        
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
        network_name: netname,
        target_id: uid,
        permissions: perm
    }, outfun );   
}



/* ==========================================================================
 * Actions for ontologies
 * ========================================================================== */


function ncCreateNewClass(netname, parentid, classname, islink, isdirectional) {
    
    // check the class name string     
    if (ncCheckString(classname, 1)<1) {
        ncMsg("Hey!", "Invalid class name");
        exit();
    }
    
    $.post(nc_api, {
        controller: "NCOntology", 
        action: "createNewClass", 
        network_name: netname,
        class_name: classname,
        parent_id: parentid,  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {
        ncAlert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            ncMsg('Error', data['errormsg']);                
        } else {                
            // insert was successful, so append the tree            
            var newrow = {
                class_id:data['data'], 
                parent_id:parentid, 
                connector:+islink,
                directional:+isdirectional, 
                class_name:classname
            };
            ncAddClassTreeChild(newrow, 1);                
        }
    });  
}


/**
 * Send a request to update a class name or parent structure
 */
function ncUpdateClassProperties(netname, classid, classname, parentid, islink, isdirectional) {
    
    //alert("in update: "+netname+" "+classid+" classname:"+classname+" parent:"+parentid+" link:"+islink+" directional:"+isdirectional);    
    if (ncCheckString(classname, 1)<1) {
        ncMsg("Hey!", "Invalid class name");
        exit();
    }
    
    $.post(nc_api, {
        controller: "NCOntology", 
        action: "updateClass", 
        network_name: netname,
        class_id: classid,
        class_name: classname,
        class_status: 1,
        parent_id: parentid,  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {
        ncAlert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            ncMsg('Error', data['errormsg']);                
        } else {   
            // update the tree display            
            var targetdisplay = $('div.nc-classdisplay[val="'+classid+'"]');
            // update the look of the form
            targetdisplay.find('.nc-classdisplay-span').html(classname);
            if (isdirectional) {
                targetdisplay.find('.nc-directional').html(' (directional)');
            } else {
                targetdisplay.find('.nc-directional').html('');
            }
        }
    });  
}


// sends a request to remove/disactivate a class
function ncRemoveClass(netname, classid) {

    //alert("in remove: "+netname+" "+classid);
        
    $.post(nc_api, {
        controller: "NCOntology", 
        action: "removeClass", 
        network_name: netname,
        class_id: classid        
    }, function(data) {
        ncAlert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            ncMsg('Error', data['errormsg']);                
        } else {   
            if (data['success']==true) {
                // the class has been truly removed
                $('li[val="'+classid+'"]').fadeOut('normal', function() {$(this).remove()} ); 
            } else {
                // the class has been deprecated
                
            }
        }
    });  

}


/* ==========================================================================
* Actions on Log page
* ========================================================================== */


/**
* Invoked from the log page when user requests a 
*/
function ncLoadActivityPage(netname, pagenum, pagelen) {    
    
    $('#nc-activity-log-toolbar li[value!='+pagenum+']').removeClass("active");
    $('#nc-activity-log-toolbar li[value='+pagenum+']').addClass("active");
    // load the 
    $.post(nc_api, 
    {
        controller: "NCNetworks", 
        action: "getNetworkActivity", 
        network_name: netname,
        offset: pagenum*pagelen,
        limit: pagelen
    }, function(data) {        
        //alert(data);  
        data = $.parseJSON(data);
        if (data['success']) {
            ncPopulateActivityArea(data['data']);
        } else {
            alert("bad response");
        }
    });

}






/* ==========================================================================
* Actions on page load
* ========================================================================== */

/**
* This fixes a "bug" in bootstrap that allows radio buttons to accept clicks
* and change active state even if they are set to disabled
*/
$(document).ready(
    function () {
        ncDisabledClick('.btn-group'); 
    }
    );



/* ==========================================================================
* misc code, leftovers
* ========================================================================== */
