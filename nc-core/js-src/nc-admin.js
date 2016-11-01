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



/* ====================================================================================
* Section on processing the network creation form
* ==================================================================================== */

/*
* Invoked from the network creation page (admin)
* Extracts values from a form and sends a network creation request to server
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
    
    // callback function displayes feedback from server into the form
    var createCallback = function(data) {
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
       
    nc.admin.sendCreateNetwork($('#'+fgname+' input').val(), $('#'+fgtitle+' input').val(),
        networkdesc, createCallback);
     
    return 1;
}


/** 
 * Sends request to create network to server
 * (Helper to nc.admin.createNetwork and nc.admin.importNetwork
 * 
 * @param netname - string with network name
 * @param nettitle - string, network title
 * @param netabstract - string, network abstract
 * @param callback - callback function that processes server response
 * 
 */
nc.admin.sendCreateNetwork = function(netname, nettitle, netabstract, callback) {    
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "createNewNetwork", 
        name: netname,
        title: nettitle,
        "abstract": netabstract,
        "content": ""
    }, function(data) {         
        nc.utils.alert(data);                
        callback(JSON.parse(data));                                
    }
    );    
}

/**
 *
 * Invoked from the network creation page (admin)
 * Send a request to create  new network from a data file
 */
nc.admin.importNetwork = function(fgfile) {
            
    // handle appearance of form
    $('#'+fgfile).removeClass('has-warning has-error');
            
    // check if filename is specified
    var fileinput = $('#'+fgfile+' input');     
    var filename = fileinput.val();
    if (filename=='') {        
        $('#'+fgfile).addClass('has-warning');
        $('#'+fgfile+' label').html("Please select a data file");
        return;
    }
    var fileurl = fileinput[0].files[0];
    
    // set up file reader and open/read the specified file
    var reader = new FileReader();
    reader.onload = function(e) {                                 
        // clean the data here with JSON.parse
        try {
            var filejson = JSON.parse(reader.result)
        } catch(ex) {
            nc.msg('Error', 'File contents does not appear to be valid JSON');                
            return;
        }
        
        // extract network name, title, description
        if (!("network" in filejson)) {
            nc.msg('Error', 'File does not contain a network definition');
        }
        var netdef = filejson.network[0];
        if (netdef==null) {
            nc.msg('Error', 'Invalid network definition');
        }        
        var netname = netdef["name"];
        var nettitle = netdef["title"];
        var netabstract = netdef["abstract"];                
        if (netname==null) {
            nc.msg('Error', 'Missing network name');
        }
        if (nettitle==null) nettitle = netname;        
        if (netabstract==null) netabstract = nettitle;
         
        // send a request to create the network                
        var createCallback = function(data) {
            if (nc.utils.checkAPIresult(data)) {
                if (data['success']==false || data['data']==false) {
                    $('#'+fgfile).removeClass('has-warning has-error');                    
                    $('#'+fgfile+' label').html("Please choose another network name");  
                    // that's all, now exit
                } else if (data['success']==true && data['data']==true) {                    
                    // here invoke function to actually send the data to the server                    
                    nc.data.sendData(filename, "new network", fileurl, netname);        
                }
            }
        }                 
        nc.admin.sendCreateNetwork(netname, nettitle, netabstract, createCallback);                        
    }
      
    reader.readAsText(fileurl);    
}


/**
 * Send a request to purge a network
 * 
 */
nc.admin.purgeNetwork = function() {
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "purgeNetwork", 
        network: nc.network        
    }, function(data) {                         
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==false) {
                nc.msg('Error', data['errormsg']);                
            } else if (data['success']==true) {                    
                nc.msg('Result', data['data']);
            }
        }
        location.reload();
    }
    ); 
}

/* ====================================================================================
* Section on processing user accounts
* ==================================================================================== */

/**
* Invoked from admin page when creating a new user.
* Extracts values from the form and sends a request to the server.
* 
* @param fgfirst - id of formgroup with the new user's firstname
* @param fgmiddle
* @param fglast
* @param fgid - formgroup with user id
* @param fgemail - formgroup with email address
* @param fgpwd - formgroup with password
* @param fgpwd2 - formgroup with password (confirmation)
* 
*/
nc.admin.createUser = function(fgfirst, fgmiddle, fglast, fgid, fgemail, fgpwd, fgpwd2) {
    
    $('form .form-group').removeClass('has-warning has-error');
        
    // basic checks on the for text boxes     
    if (nc.utils.checkFormInput(fgfirst, "first name", 1) 
        + nc.utils.checkFormInput(fgmiddle, "middle name", -1)
        + nc.utils.checkFormInput(fglast, "last name", 1)
        + nc.utils.checkFormInput(fgid, "user id", 1)
        + nc.utils.checkFormInput(fgpwd, "password", 1)
        + nc.utils.checkFormInput(fgpwd2, "password", 1)
        + nc.utils.checkFormEmail(fgemail, "email") < 7) {
        return 0;
    };                
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
        target_id: $('#' +fgid+' input').val(),        
        target_password: $('#'+fgpwd+' input').val()
    }, function(data) {          
        nc.utils.alert(data);        
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==false || data['data']==false) {
                $('#'+fgid).addClass('has-error has-feedback');                
                $('#'+fgid+' label').html("Please choose another user id:");                
            } else if (data['success']==true && data['data']==true) {                 
                $('form .form-group').addClass('has-success has-feedback');                                                                
                $('form button.submit').removeClass('btn-success').addClass('btn-default disabled').html("Success!");                
                $('form,form button.submit').attr("disabled", true);  
            }
        }                        
    });    
    
    return 1;
}

