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



/* ====================================================================================
 * Importing files
 * ==================================================================================== */


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
                                                            
    // ask for confirmation using a modal box
    var confirmmodal = $('#nc-data-modal');
    confirmmodal.find('#nc-dataconfirm-file').html(filename);
    confirmmodal.find('button[val="nc-confirm"]').off("click").click(function() {
        nc.data.sendData(filename, filedesc, fileurl, nc.network);
    });
    confirmmodal.find('p[val="download"]').hide();
    confirmmodal.find('p[val="upload"]').show();    
    confirmmodal.modal("show");    
   
    return false;   
}


/**
 * Invoked after user confirms upload of data.
 * @params filename - string, name of file to upload
 * @params filedesc - string, short description recorded during upload
 * @params fileurl - complete url to local file
 * @param networkname - string, specifies target network
 */
nc.data.sendData = function(filename, filedesc, fileurl, networkname) {
        
    var btn = $('#nc-import-form button[type="submit"]');
    btn.toggleClass("btn-success btn-default disabled").html("Uploading (please wait)");
    
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
                
        $.post(nc.api, 
        {
            controller: "NCData", 
            action: "importData", 
            network: networkname,
            file_name: filename,
            file_desc: filedesc,
            data: filedata
        }, function(data) { 
            alert(data);
            nc.utils.alert(data);        
            data = JSON.parse(data);            
            if (nc.utils.checkAPIresult(data)) {
                if (data['success']==false) {
                    $('#nc-import-response').html("Error: "+data['errormsg']);            
                } else {                                        
                    $('#nc-import-response').html(data['data'].replace(/\n/g,"<br/>"));            
                }             
            } else {                
                $('#nc-import-response').html("something went wrong...");            
            }                        
            btn.toggleClass("btn-default disabled btn-success").html("Submit");
        }
        );    
    };   
    reader.readAsText(fileurl);    


}
