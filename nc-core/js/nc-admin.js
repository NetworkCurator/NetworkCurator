/* 
 * nc-admin.js
 * 
 * Administrator-specific functions (creating new users, creating new networks)
 * 
 */


if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.admin = {};



/* ==========================================================================
* Section on processing the network creation form
* ========================================================================== */

/*
* Invoked from the network creation page (admin)
* Extracts values from the form and sends a network creation request to server
*  
* @param fgname - id of formgroup containing network name
* @param fgtitle - id of formgroup containing network title
* @param fgdesc - id of formgroup containign network description/abstract
*/ 
nc.admin.createNetwork = function(fgname, fgtitle, fgdesc) {

    $('#'+fgname+',#'+fgtitle+',#'+fgdesc).removeClass('has-warning has-error');
    // basic checks on the network name text box    
    if (nc.utils.checkFormInput(fgname, "network name", 1)+
        nc.utils.checkFormInput(fgtitle, "network title", 2) < 2) return 0;    
    var networkdesc = $('#'+fgdesc+' textarea').val();
    if (networkdesc.length>250) return 0;
       
    // post the registration request 
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "createNewNetwork", 
        network_name: $('#'+fgname+' input').val(),
        network_title: $('#'+fgtitle+' input').val(),
        network_desc: networkdesc
    }, function(data) {          
        nc.utils.alert(data);        
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==false || data['data']==false) {
                $('#'+fgname).addClass('has-error has-feedback');                
                $('#'+fgname+' label').html("Please choose another network name:");                
            } else if (data['success']==true && data['data']==true) {                    
                $('#'+fgname+',#'+fgtitle+',#'+fgdesc).addClass('has-success has-feedback');                                
                $('form button.submit').removeClass('btn-success').addClass('btn-default disabled').html("Success!");                
                $('form,form button.submit').attr("disabled", true);                
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
* @param fgfirst - id of formgroup with the new user's firstname
* @param fgmiddle
* @param fglast
* @param fgname - formgroup with user id
* @param fgemail - formgroup with email address
* @param fgpwd - formgroup with password
* @param fgpwd2 - formgroup with password (confirmation)
* 
*/
nc.admin.createUser = function(fgfirst, fgmiddle, fglast, fgname, fgemail, fgpwd, fgpwd2) {
    
    $('form .form-group').removeClass('has-warning has-error');
        
    // basic checks on the for text boxes     
    if (nc.utils.checkFormInput(fgfirst, "first name", 1) 
        + nc.utils.checkFormInput(fgmiddle, "middle name", -1)
        + nc.utils.checkFormInput(fglast, "last name", 1)
        + nc.utils.checkFormInput(fgname, "user id", 1)
        + nc.utils.checkFormInput(fgpwd, "password", 1)
        + nc.utils.checkFormInput(fgpwd2, "password", 1)
        + nc.utils.checkFormEmail(fgemail, "email") < 7) { return 0; };                
    if ($('#'+fgpwd+' input').val() != $('#'+fgpwd2+' input').val()) {
        $('#'+fgpwd2).addClass('has-warning');
        $('#'+fgpwd2+' label').html("Please re-confirm the password:");
        return 0;
    }                      
              
    $.post(nc.api, 
    {
        controller: "NCUsers", 
        action: "createNewUser", 
        target_firstname: $('#'+fgfirst +' input').val(),
        target_middlename: $('#'+fgmiddle+' input').val(),
        target_lastname: $('#'+fglast+' input').val(),
        target_email: $('#'+fgemail+' input').val(),
        target_name: $('#' +fgname+' input').val(),        
        target_password: $('#'+fgpwd+' input').val()
    }, function(data) {          
        nc.utils.alert(data);        
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==false || data['data']==false) {
                $('#'+fgname).addClass('has-error has-feedback');                
                $('#'+fgname+' label').html("Please choose another user id:");                
            } else if (data['success']==true && data['data']==true) {                 
                $('form .form-group').addClass('has-success has-feedback');                                                                
                $('form button.submit').removeClass('btn-success').addClass('btn-default disabled').html("Success!");                
                $('form,form button.submit').attr("disabled", true);  
            }
        }                        
    });    
    
    return 1;
}

