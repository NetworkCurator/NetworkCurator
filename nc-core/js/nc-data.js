/* 
 * nc-data.js
 * 
 * Data import/export functions
 * 
 */


if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.data = {};



/* ==========================================================================
 * Importing files
 * ========================================================================== */


/*
 * Invoked from the data import form 
 * Extracts values from the form and sends a data upload request to server
 *  
 * @param fgfile - id of formgroup containing import file
 * @param fgdesc - id of formgroup containing import message/description
 *
 */ 
nc.data.importData = function(fgfile, fgdesc) {
    
    $('#'+fgfile+',#'+fgdesc).removeClass('has-warning has-error');
    // basic checks on the network name text box    
    if (nc.utils.checkFormInput(fgdesc, "description", 2)<1) return 0;    
    var filedesc = $('#'+fgdesc+' input').val();
    if (filedesc.length>128) return 0;
        
    // check if filename is specified
    var fileinput = $('#'+fgfile+' input');     
    var filename = fileinput.val();
    if (filename=='') {        
        $('#'+fgfile).addClass('has-warning');
        $('#'+fgfile+' label').html("Please select a data file");
    }
    var fileurl = fileinput[0].files[0];
                    
    var btn = $('#nc-import-form button[type="submit"]');
    btn.toggleClass("btn-success btn-default disabled").html("Uploading");
                    
    // set up file reader and open/read the specified file
    var reader = new FileReader();
    reader.onload = function(e) {                                 
        // clean the data here with JSON.parse
        try {
            var filedata = JSON.stringify(JSON.parse(reader.result))
        } catch(ex) {
            nc.msg('Error', 'File contents does not appear to be valid JSON');                
            return;
        }
        
        //alert("here "+nc.network+" "+filename+" "+filedata);
        $.post(nc.api, 
        {
            controller: "NCData", 
            action: "importData", 
            network_name: nc.network,
            file_name: filename,
            file_desc: filedesc,
            file_content: filedata
        }, function(data) {          
            alert(data);
            nc.utils.alert(data);        
            data = JSON.parse(data);
            if (nc.utils.checkAPIresult(data)) {
                if (data['success']==false || data['data']==false) {
                } else if (data['success']==true && data['data']==true) {                    
                }
            }                        
            btn.toggleClass("btn-default disabled btn-success").html("Submit");
        }
        );    
    };   
    reader.readAsText(fileurl);    
                    
    return false;   
}