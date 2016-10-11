/* 
 * nc-core.js
 *  
 * 
 * 
 */


/**
 * nc is the main object/namespace for the NetworkCurator
 * 
 * The nc namespace holds data relevant to the current user and the current page.
 * 
 * Sub-namespaces hold functions relevant for manipulating objects, dealing
 * with the user interface, or communicating with the server.
 *   
 */  
var nc = {
    // initialize the object with the current username
    userid: '',
    firstname: '',
    middlename: '',
    lastname: '',
    // the current network name
    network: '',
    networktitle : '',
    // objects holding markdown and comment content
    md: {},
    comments: {},
    // settings for permissions
    commentator: 0,
    curator: 0,
    editor: 0, 
    // location of server-side api
    api: window.location.href.split('?')[0]+"nc-core/networkcurator.php",   
    // sub-namespaces
    admin: {},    
    classes: {},    
    graph: {},
    init: {},
    permissions: {},    
    users: {},
    ui: {},
    utils: {}    
};



/* ====================================================================================
* Generic functions 
* ==================================================================================== */

/**
* Show a message in a modal window
*/
nc.msg = function(h, b) {
    
    $('#nc-msg-header').html(h);
    $('#nc-msg-body').html(b);
    $('#nc-msg-modal').modal('show');
}


/* ====================================================================================
 * Startup
 * ==================================================================================== */

/**
 * runs several startup functions. Each function determines if its content
 * is relevant for the current page and either returns quickly or performs its
 * startup duties.
 */
nc.init.all = function() {        
    var speed = nc.ui.speed;
    nc.ui.speed = 0;
    
    nc.init.initNetwork();
    nc.init.initPermissions();
    nc.init.initLog();
    //alert("going to initCuration");
    nc.init.initCuration();
    //alert("going to initMarkdown");
    nc.init.initMarkdown();
    //alert("going to initComments");    
    nc.init.initComments();        
    nc.init.initOntology();    
    nc.init.initGraph();       
    
    nc.ui.speed = speed;
}

/**
 * Invoked at page startup - builds widgets for managing guest and user permissions 
 */
nc.init.initPermissions = function() {        
    // check if this is the permissions page
    var guestperms = $('#nc-permissions-guest');
    var usersperms = $('#nc-permissions-users');
    if (guestperms.length==0 || usersperms.length==0) return;
        
    // creat widgets
    guestperms.append(nc.ui.PermissionsWidget(nc.permissions.guest));                
    usersperms.append(nc.ui.PermissionsWidget(nc.permissions.users));                          
    // prevent disabled buttons being clicked
    $('.btn-group .disabled').click(function(event) {
        event.stopPropagation();
    }); 
}

/**
 * Invoked at startup to generate ontology trees
 * 
 */
nc.init.initOntology = function() {        
    // compute long names for classes that include hierarchy structure
    nc.ontology.nodes = nc.ontology.addLongnames(nc.ontology.nodes);
    nc.ontology.links = nc.ontology.addLongnames(nc.ontology.links);    
    
    // check if this function applies on the page
    var ontnodes = $('#nc-ontology-nodes');
    var ontlinks = $('#nc-ontology-links');        
    if (ontnodes.length==0 || ontlinks.length==0) return;
         
    // add ontology trees     
    ontnodes.html(nc.ui.ClassTreeWidget(nc.ontology.nodes, false));                    
    ontlinks.html(nc.ui.ClassTreeWidget(nc.ontology.links, true));               
}

/** 
 * Run at page startup to create a log widget with buttons and log content
 * 
 */
nc.init.initLog = function() {      
    // check if the log div is present 
    var logdiv = $('#nc-activity-log');
    if (logdiv.length==0) return;        
      
    // fetch the total number of rows and a first set of log data
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "getActivityLogSize", 
        network: nc.network        
    }, function(data) {  
        data = JSON.parse(data);
        var logsize = +data['data'];
        logdiv.append(nc.ui.ActivityLogToolbar(logsize, 50));           
        nc.loadActivity(0, 50);
    });  
}

/**
 * Initialize a graph editing toolbar and graph viewer
 */
nc.init.initGraph = function() {
    if ($('#nc-graph-svg').length==0) return;    
    nc.graph.initToolbar();    
    nc.graph.initSimulation();
    nc.graph.simUnpause();
}

/**
* run at startup, fetches comments associated with the nc-comments box.
*/
nc.init.initComments = function() {
        
    // find out if this page has a space for comments
    var cbox = $('#nc-comments');
    if (cbox.length==0) {
        return;
    }
        
    // fetch all comments    
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "getComments", 
        network: nc.network, 
        root_id: cbox.attr("val")
    }, function(data) {        
        //nc.utils.alert(data);  
        data = JSON.parse(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);  
            return;
        } else {       
            // populate the comments box with the new comment
            nc.ui.populateCommentsBox(data['data']);        
        }
    });
    
    // for users allowed to generate comments, add a comment box    
    if (!nc.commentator) {
        return;
    }
    
    var rootid = $('#nc-newcomment').attr('val');
    $('#nc-newcomment').html(nc.ui.CommentBox(nc.userid, rootid, rootid, '', ''));
}

/**
 * For all users, sets up boxes that display markdown content. 
 * For curators, provides access to page toggling
 * 
 */
nc.init.initCuration = function() {
    
    // all users need to have the anno edit boxes
    // these boxes actually displays content.        
    var box = nc.ui.AnnoEditBox();
    var alleditable = $('.nc-editable-text');    
    alleditable.html(box);    
             
    // other functions are for curators only    
    // if (!nc.curator) return;         
    
    // show curation level ui graphics, add an event to toggle curation on/off
    $('.nc-curator').show();    
    var lockbtn = $('#nc-curation-lock');
    lockbtn.on("click",
        function() {
            lockbtn.find('span.glyphicon-pencil, span.glyphicon-lock').toggleClass('glyphicon-pencil glyphicon-lock');
            lockbtn.toggleClass("nc-editing nc-looking"); 
            // for curators, all editable components become active,
            // for non-curators, only those components that are owned by them
            var targets = (nc.curator ? $('.nc-editable-text') : $('.nc-editable-text[owner="'+nc.userid+'"]'));            
            targets.toggleClass('nc-editable-text-visible');
        });    
    
    $('.nc-curation-toolbox').css("font-size", $('body').css("font-size"));   
}

/**
* run at startup to convert data in a global object nc_md into html
* within page elements
*/
nc.init.initMarkdown = function() {
    
    // convert the md content into html
    $.each(nc.md, function(key, val) {           
        var temp = $('.nc-md[val="'+key+'"]');
        var nowarea = temp.find('textarea.nc-curation-content');
        // convert md into html, then into alive html
        var html = nc.utils.md2html(val);        
        if (nowarea.length>0) {
            // this element is marked as editable and thus should have a textarea 
            // and content div
            temp.find('textarea.nc-curation-content').html(val);                         
            temp.find('div.nc-curation-content').html(html);
        } else {
            // element is not marked as editable. Just show it
            temp.html(html);
        }            
    });        
}


/**
 * When page holds a specific network title, it displays the title in the navbar
 */
nc.init.initNetwork = function() {   
    
    if (nc.networktitle!='') {
        $('#nc-nav-network-title').html(nc.networktitle);        
        $('body').addClass('nc-body2');
    }
    
    // hide certain components depending on current users permissions level
    if (!nc.curator) $('.nc-curator').hide();        
    if (!nc.editor) $('.nc-editor').hide();    
    if (!nc.commentator) $('.nc-commentator').hide();    
}


/* ====================================================================================
* Actions on Log page
* ==================================================================================== */

/**
* Invoked from the log page when user requests a page of the log
* 
* @param pagenum - integer, page number of log to load 
* (i.e. 0 to get first page of the log, 1 to skip the first entries and show the
* next batch, etc)
* @param pagelen - integer, number of entries per page of the log 
* 
*/
nc.loadActivity = function(pagenum, pagelen) {    
    
    $('#nc-activity-log li[value!='+pagenum+']').removeClass("active");
    $('#nc-activity-log li[value='+pagenum+']').addClass("active");
   
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "getNetworkActivity", 
        network: nc.network,
        offset: pagenum*pagelen,
        limit: pagelen
    }, function(data) {                 
        data = JSON.parse(data);
        if (data['success']) {
            nc.ui.populateActivityArea(data['data']);
        } else {
            nc.msg(data['data']);
        }
    });

}



/* ====================================================================================
* Annotation updates
* ==================================================================================== */

/**
* This sends an update request to the server
* 
* @param annoid - id of annotation to modify
* @param annomd - new markdown text for the annotation
* 
*/
nc.updateAnnotationText = function(annoid, annomd) {  

    if (nc.network=='') return;        
    if (annomd=='') {
        nc.msg("Hey", "Annotation text cannot be blank");
        return;
    }
    
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "updateAnnotationText", 
        network: nc.network,
        anno_id: annoid,
        anno_text: annomd
    }, function(data) {           
        nc.utils.alert(data);  
        data = JSON.parse(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);            
        }        
    });
}


/* ====================================================================================
* Commenting
* ==================================================================================== */


/**
* run when user presses "save" and tries to submit a new comment
*/
nc.createComment = function(annomd, rootid, parentid) {
        
    if (nc.network=='') return; 
    
    // avoid sending a comment that is too short
    if (annomd.length<2) return;        

    // provide click feedback
    $('#nc-newcomment a.nc-submit').removeClass('btn-success').addClass('btn-default')
    .html('Sending...');
                
    // send a request to the server
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "createNewComment", 
        network: nc.network, 
        root_id: rootid,
        parent_id: parentid,
        anno_text: annomd
    }, function(data) {                
        nc.utils.alert(data);  
        data = JSON.parse(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);      
            return;
        }
        // provide feedback in the button
        $('#nc-newcomment a.nc-submit').html('Done');        
        setTimeout(function(){
            $('#nc-newcomment a.nc-submit').addClass('btn-success').removeClass('btn-default').html('Submit');
        }, this.timeout);         
        // add the comment to the page       
        var comdata = {
            datetime: 'just now', 
            modified: null, 
            user_id: nc.userid, 
            owner_id: nc.userid, 
            root_id: rootid,
            parent_id: parentid, 
            anno_id: data['data'], 
            anno_text: annomd
        };        
        nc.ui.addCommentBox(comdata);
        if (rootid!=parentid) {
            $('.media-body .media[val=""]').hide();    
        }
        $('#nc-newcomment textarea').val('');
    });

}


/* ====================================================================================
* Actions on page load
* ==================================================================================== */

/**
* This starts the page initialization code
*/
$(document).ready(
    function () {
        nc.init.all();      
    });   
   /* 
 * nc-utils.js
 * 
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.utils = {};


/* ====================================================================================
 * Debugging (during development)
 * ==================================================================================== */

/**
 * for debugging, turn on/off
 */
nc.utils.debug = false;


/**
 * Create an alert message, but only when debugging is turned on
 */
nc.utils.alert =function(x) {    
    if (nc.utils.debug) alert(x);
}



/* ====================================================================================
 * Some general purpose functions (e.g. string and form checking)
 * ==================================================================================== */


/**
 * Check if a string is composed of a proper combination of characters
 * 
 * @param x - an input string
 * @param type - integer code. 
 *   use 0 for id-like strings (strictly alphanumeric, minimum length)
 *   use 1 for lenient id-like strings (alphanumeric, with _-)
 *   use 2 for name-like strings (spaces, dashes, and apostrophe allowed)
 *   use 3 for passwords (special chars allowed, minimum length 6) 
 *   
 *   use negative values to skip the length requirement
 */
nc.utils.checkString = function(x, type) {
            
    // define characters that are allowed in the string x
    var ok = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if (type==1) {
        ok = ok + "_-";
    } else if (type==2) {
        ok = ok + " '-";
    } else if (type==3) {
        ok = ok + "_-$-.+!*'(),";
    }
        
    // ids cannot start with an underscore
    if (type==1) {
        if (x[0]=="_") return 0;
    }
        
    // perform length and composition checks    
    var xlen = x.length;
    if (type>=0) {
        if (xlen<2) return 0;    
        if (type==3 && xlen<7) return 0;            
    }
    for (var i=0; i<xlen; i++) {        
        if (ok.indexOf(x[i])<0) return 0; 
    }
    
    return 1;	
}

/**
* Checks types within input elements in a form group div
* 
* @param fgid - string, id of formgroup; function looks for input or textarea within this fg
* @param fgname - longer text, used to update a label in the form group
* @param type - integer, used in conjuction with nc.checkString
* 
* @return 
* 1 if the input value matches the type
* 0 if there is a problem (also updates the ui)
* 
*/
nc.utils.checkFormInput = function(fgid, fgname, type) {
    var checkelement = '#'+fgid+' input,#'+fgid+' textarea';    
    if (nc.utils.checkString($(checkelement).val(), type)==0) {    
        $('#'+fgid).addClass('has-warning');
        $('#'+fgid+' label').html("Please enter a (valid) "+fgname+":");
        return 0;
    }   
    return 1;
}


/**
* Tests if email in a formgroup input is well-formed
* 
* @param fgid - an id of a formgroup containing an input with email
*/ 
nc.utils.checkFormEmail= function(fgid) {
    var ee = $('#'+fgid+' input').val();
    var la = ee.indexOf('@');
    if (la== -1 || ee.indexOf('.',la)<2) {
        $('#'+fgid).addClass('has-warning');
        $('#'+fgid+' label').html("Please enter a (valid) email:");
        return 0;		
    }
    return 1;
}

/**
 * checks that an object x is consistent with an API return 
 * 
 * @param x array parsed from API response
 * 
 */
nc.utils.checkAPIresult = function(x) {    
    if ("success" in x && ("data" in x || "errormsg" in x)) {
        return true;
    } else {
        nc.utils.alert("Something wrong in API result: "+ JSON.stringify(x));        
        return false;
    }
}



/** 
 * Helper function sorts an array by one of the elements (key)
 * 
 * @param arr array each element is assumed to be another sub-array
 * @param key one of the keys in the sub-arrays
 */
nc.utils.sortByKey = function(arr, key) {
    
    return arr.sort(function(a, b) {
        var x = a[key];
        var y = b[key];
        if (x<y) {
            return -1;
        } else if (x>y) {
            return 1;
        } else {
            return 0;
        }        
    });
}



/* ====================================================================================
 * For markdown conversions and html handling
 * ==================================================================================== */


// markdown converter
nc.utils.mdconverter = new showdown.Converter({
    headerLevelStart: 1, 
    tables: true,
    strikethrough: true,
    tasklists: true
});


// allowed tags for sanitize-html
// compare to sanitize-html default, adds svg tags
nc.utils.allowedTags = ['h3', 'h4', 'h5', 'h6', 'blockquote', 
    'p', 'a', 'ul', 'ol',
    'nl', 'li', 'b', 'i', 'strong', 'em', 'strike', 'code', 'hr', 'br', 'div',
    'table', 'thead', 'caption', 'tbody', 'tr', 'th', 'td', 'pre',   
    'circle', 'rect', 'line', 'path', 'polyline', 'ellipse', 'polygon', 'marker'];
nc.utils.allowedAttributes= {
    code: ['class'],
    style: ['type'],
    circle: ['cx', 'cy', 'r', 'id' , 'fill'],
    rect: ['x', 'y', 'width', 'height', 'id', 'fill'],
    marker: ['id', 'viewbox', 'refX', 'refY', 'markerWidth', 'markerHeight', 'orient'],
    line: ['x1', 'x2', 'y1', 'y2', 'id'],
    path: ['d', 'fill']
}


// conversion from markdown to html (sanitized and alive)
nc.utils.md2html = function(x) {
    var x2 = nc.utils.sanitize(nc.utils.mdconverter.makeHtml(x), false);
    return mdalive.makeAlive(x2);   
}


// sanitize a piece of html
// x - a string with text (dirty html)
// allowstyle - logical. Set true to allow the <style> tag.
nc.utils.sanitize =function(x, allowstyle) {    
    var oktags = nc.utils.allowedTags.slice(0);
    if (allowstyle) {
        oktags.push('style');
    }    
    return sanitizeHtml(x, {
        allowedTags: oktags,
        allowedAttributes: nc.utils.allowedAttributes 
    });    
}


/* ====================================================================================
 * SVG-related hacks
 * ==================================================================================== */

// hacks to get an SVG to definitely update after its defs are set
// from: https://jcubic.wordpress.com/2013/06/19/working-with-svg-in-jquery/
$.fn.xml = function() {
    return (new XMLSerializer()).serializeToString(this[0]);
};

$.fn.DOMRefresh = function() {
    return $($(this.xml()).replaceAll(this));
};
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
        nc.data.sendData(filename, filedesc, fileurl);
    });
    confirmmodal.find('p[val="download"]').hide();
    confirmmodal.find('p[val="upload"]').show();    
    confirmmodal.modal("show");    
   
    return false;   
}


/**
 * Invoked after user confirms upload of data.
 * 
 */
nc.data.sendData = function(filename, filedesc, fileurl) {
        
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
            network: nc.network,
            file_name: filename,
            file_desc: filedesc,
            file_content: filedata
        }, function(data) {                                  
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
/* 
 * nc-graph.js
 * 
 * Functions that deal with graph display/creation/editing
 * 
 * This file mixes jquery and d3 frameworks - perhaps shift entirely to d3?
 * 
 * Helpful blocks:
 * https://bl.ocks.org/curran/9b73eb564c1c8a3d8f3ab207de364bf4
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.graph = {
    rawnodes: [], // store for raw data on nodes
    rawlinks: [], // store for raw data on links
    nodes: [], // store active nodes in the viz
    links: [], // store active links in the viz
    info: {}, // store of downloaded content information about a node/link
    sim: {}, // force simulation 
    svg: {}, // the svg div on the page   
    mode: "select"
};


/* ====================================================================================
 * Structure of graph box
 * ==================================================================================== */

/**
 * Build a toolbar and deal with node/link classes
 */
nc.graph.initToolbar = function() {
        
    // check if this is the graph page
    var toolbar = $('#nc-graph-toolbar');
    nc.graph.svg = d3.select('#nc-graph-svg');   
        
    // get descriptions of the node and link ontology    
    var pushonto = function(x) {
        var result = [];
        $.each(x, function(key, val) {            
            result.push({
                id: val['class_id'],
                label: val['longname'], 
                val: val['name'],
                status: val['status']
            });
        });        
        return nc.utils.sortByKey(result, 'label');
    }    
    var nodes=pushonto(nc.ontology.nodes), links=pushonto(nc.ontology.links);
     
    // add ontology options to the new node/new link forms   
    $('#nc-graph-newnode #fg-nodeclass .input-group-btn')
    .append(nc.ui.DropdownButton("", nodes, "node", false).find('.btn-group'))
    $('#nc-graph-newlink #fg-linkclass .input-group-btn')
    .append(nc.ui.DropdownButton("", links, "link", false).find('.btn-group'))
    
    // add buttons to the toolbar, finally!
    toolbar.append(nc.ui.ButtonGroup(['Select']));
    toolbar.append(nc.ui.DropdownButton("New node:", nodes, "node", false));
    toolbar.append(nc.ui.DropdownButton("New link:", links, "link", false));     
    
    toolbar.find("button").click(function() {
        toolbar.find("button").removeClass("active");         
    })    
    
    // create behaviors in the svg based on the toolbar
    
    // create handlers    
    var jsvg = $('#nc-graph-svg');
    jsvg.mouseenter(function() {   
        var newnode = $('#nc-graph-toolbar button[val="node"]');
        var newlink = $('#nc-graph-toolbar button[val="link"]');            
        if (newnode.hasClass("active")) {
            jsvg.css("cursor", "crosshair");   
            nc.graph.svg.on("click", nc.graph.addNode); 
            nc.graph.mode = "newnode";
        } else if (newlink.hasClass("active")) {
            jsvg.css("cursor", "crosshair");        
            nc.graph.svg.on("click", nc.graph.addLink)            
            nc.graph.mode = "newlink";
        } else {
            jsvg.css("cursor", "grab");   
            nc.graph.svg.selectAll('g').attr('cursor', 'default');
            nc.graph.svg.on("click", null);            
            nc.graph.mode = "select";
        }       
    });
  
}



/* ====================================================================================
 * Handlers for graph editing
 * ==================================================================================== */

// invoked by mouse click
nc.graph.addNode = function() {
    // find the current node type, then add a classed node 
    var whichnode = d3.select('#nc-graph-toolbar button[val="node"]');
    var classid = whichnode.attr('class_id');
    var classname = whichnode.attr('class_name');
    var newnode = nc.graph.addClassedNode(nc.graph.getCoord(d3.mouse(this)), classid, classname, "nc-newnode");            
    nc.graph.select(newnode);
    return newnode;
}

// helper function, invoked from addNode()
nc.graph.addClassedNode = function(point, classid, classname, styleclass) {     
    var newid = "+"+classname+"_"+Date.now(),    
    newnode = {
        "id": newid,
        "name": newid,
        "class_id": classid,
        "class_name": classname,
        "class": styleclass,
        "status": 1,
        x: point[0], 
        y: point[1],
        fx: point[0], 
        fy: point[1]
    };    
    //alert(JSON.stringify(newnode));
    nc.graph.simStop();
    nc.graph.rawnodes.push(newnode);          
    nc.graph.initSimulation(); 
    
    return newnode;
}

// invoked by mouse events
nc.graph.addLink = function() {
    var whichlink = d3.select('#nc-graph-toolbar button[val="link"]');    
    var classid = whichlink.attr("class_id");
    var classname =  whichlink.attr("class_name");
    var newlink = nc.graph.addClassedLink(classid, classname, "nc-newlink");
    nc.graph.select(newlink);    
    return newlink;
}

// helper function, invoked from addLink()
nc.graph.addClassedLink = function(classid, classname, styleclass) {   
   
    // identify the temporary link
    var draggedlink = nc.graph.svg.select('line[class="nc-draggedlink"]');       
    
    // check that the link is proper
    if (draggedlink.attr("source")===null || draggedlink.attr("target")===null) {
        return null;
    }
    
    // create a new entity for the links array
    var newid = "+"+classname+"_"+Date.now();
    var newlink = {
        "id": newid,
        "class_id": classid,
        "class_name": classname,
        "status": 1,
        "class": styleclass,
        "source": draggedlink.attr("source"),
        "target": draggedlink.attr("target")       
    }
    
    // update the simulation
    nc.graph.simStop();
    nc.graph.rawlinks.push(newlink);
    nc.graph.initSimulation();
    
    return newlink;
}


/**
 * Run when user clicks on a node or link in the graph
 */
nc.graph.select = function(d) {
          
    if (d==null) {
        return;
    }

    // un-highlight existing, then highlight required id
    nc.graph.unselect();    
    if ("source" in d) {
        nc.graph.svg.select('line[id="'+d.id+'"]').classed('nc-link-highlight', true);
    } else {
        nc.graph.svg.select('use[id="'+d.id+'"]').classed('nc-node-highlight', true);       
    }    
        
    var nowid = d.id;    
    if (nowid[0]=="+") {
        $('#nc-graph-details').hide();  
        nc.graph.resetForms();
        if ("source" in d) {            
            // selected a link  
            var newlinkdiv = $('#nc-graph-newlink');                                              
            // transfer the temporary link id into the create box 
            $('#nc-graph-newlink #fg-linkname input').val(nowid);
            $('#nc-graph-newlink #fg-linktitle input').val(nowid);
            $('#nc-graph-newlink form').attr('val', nowid);
            // set the dropdown with the class
            newlinkdiv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newlinkdiv.find('button.dropdown-toggle').attr('selection', d.class_name);
            // set the source and target text boxes
            $('#nc-graph-newlink #fg-linksource input').val(d.source.name);
            $('#nc-graph-newlink #fg-linktarget input').val(d.target.name);
            newlinkdiv.show();
        } else {
            // selected a node            
            var newnodediv = $('#nc-graph-newnode')            
            // transfer the temporary node id into the create box
            $('#nc-graph-newnode #fg-nodename input').val(nowid);
            $('#nc-graph-newnode #fg-nodetitle input').val(nowid);
            $('#nc-graph-newnode form').attr('val', nowid);
            // set the dropdown with the class
            newnodediv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newnodediv.find('button.dropdown-toggle').attr('selection', d.class_name);        
            newnodediv.show(); 
        }    
    } else {        
        nc.graph.displayInfo(d);
    }
                    
}

/**
 * Remove all highlight styling from components in the graph
 */
nc.graph.unselect = function(d) {
    nc.graph.svg.selectAll('use').classed('nc-node-highlight',false);
    nc.graph.svg.selectAll('line').classed('nc-link-highlight',false);  
    $('#nc-graph-newnode,#nc-graph-newlink,#nc-graph-details').hide();    
}


/**
 * @param d
 * 
 */
nc.graph.displayInfo = function(d) {     
    
    if (d.id==null) {
        return;
    }
    
    var prefix = "nc-graph-details";
    
    // get the details div and clear its content
    var detdiv = $('#'+prefix);
    detdiv.find('.nc-md').html("Loading...");
    detdiv.find('#'+prefix+'-more').click(function() { 
        var type = ("source" in d ? "link" : "node");        
        window.location.replace("?network="+nc.network+"&object="+d.id);        
    } );
    detdiv.show();
    
    // perhaps fetch the data from server, or look it up in memory
    if (!(d.id in nc.graph.info)) {
        nc.graph.getInfo(d);
        return;
    }
        
    // if reached here, the graph info has data on this object        
    var dtitle = nc.graph.info[d.id]['title'];
    var dabstract = nc.graph.info[d.id]['abstract'];            
    var nowtitle = nc.utils.md2html(dtitle['anno_text']);
    detdiv.find('#'+prefix+'-title').html(nowtitle).attr("val", dtitle['anno_id']);
    var nowabstract = nc.utils.md2html(dabstract['anno_text']);
    detdiv.find('#'+prefix+'-abstract').html(nowabstract).attr("val", dabstract['anno_id']); 
            
    // also fill in the ontology class 
    detdiv.find('#'+prefix+'-class').html(d['class']);
        
}



/**
 * Fetch summary data associated with an object id
 * 
 * This function does not do any user-interface modifications.
 * It just fetches the data and stores it into the info object.
 * Upon completion, it calls nd.graph.select(d) to again select the object and 
 * actually display the information.
 * 
 */
nc.graph.getInfo = function(d) {    
    // send a request to server
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "getSummary", 
        network: nc.network,
        root_id: d.id,
        name: 1,
        title: 1,
        'abstract': 1,
        content: 0
    }, function(data) {                      
        //alert(data);
        nc.utils.alert(data);        
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            // push the obtained data into the info object (avoids re-fetch later)
            nc.graph.info[d.id] = data['data'];                             
        } else {
            nc.graph.info[d.id] = {
                "title": "NA", 
                "abstract": "NA"
            };            
        }                              
        nc.graph.select(d);
    });
}

/* ====================================================================================
 * Node/Link filtering
 * ==================================================================================== */


nc.graph.filterNodes = function() {         
        
    // convenience shallow copies of nc.graph objects
    var rawnodes = nc.graph.rawnodes;
    var nonto = nc.ontology.nodes;    
    var nlen = rawnodes.length;
    
    // loop and deep copy certain nodes from raw to new
    var counter = 0;
    nc.graph.nodes = [];
    for (var i=0; i<nlen; i++) {
        // get input class id
        var iclassid = rawnodes[i].class_id;         
        if (nonto[iclassid].status>0) {
            nc.graph.nodes[counter] = rawnodes[i];
            counter++;
        }        
    }
    
}

nc.graph.filterLinks = function() {
    //alert("filtering links "+nc.graph.rawlinks.length);
    
    // some shorthand objects
    var rawlinks = nc.graph.rawlinks;
    var llen = rawlinks.length;
    var lonto = nc.ontology.links;

    // get an array of all available nodes
    var goodnodes = {};
    for (var j=0; j<nc.graph.nodes.length; j++) {
        goodnodes[nc.graph.nodes[j].id]= 1;
    }
    //alert(JSON.stringify(goodnodes));

    var counter = 0;
    nc.graph.links = [];    
    //alert(nc.graph.links.length+" "+nc.graph.rawlinks.length);
    for (var i=0; i<llen; i++) {
        var iclassid = rawlinks[i].class_id;        
        if (lonto[iclassid].status>0) {
            // must also check if source and end nodes should be displayed            
            var isource = rawlinks[i].source;
            var itarget = rawlinks[i].target;
            if ((isource in goodnodes || isource.id in goodnodes) && 
                (itarget in goodnodes || itarget.id in goodnodes)) {                                
                nc.graph.links[counter] = nc.graph.rawlinks[i];
                counter++;
            }                                    
        }
    }

}


/* ====================================================================================
 * Graph display
 * ==================================================================================== */


/**
 * Extract the current transform values in the graph svg
 */
nc.graph.getTransformation = function() {        
    var content = nc.graph.svg.select("g.nc-svg-content");     
    if (content.empty()) {        
        return [0,0,1];
    }
    var trans = content.attr("transform").split(/\(|,|\)/);            
    return [parseFloat(trans[1]), parseFloat(trans[2]), parseFloat(trans[4])];          
}

/**
 * This function initializes the behavior of the graph svg simulation (force layout)
 * and some core svg components like zoom and pan.
 * 
 * Uses D3.
 *
 */
nc.graph.initSimulation = function() {
                    
    // get the graph svg component
    nc.graph.svg = d3.select('#nc-graph-svg'); 
    // fetch the existing transform and scale, if any
    var initT = nc.graph.getTransformation();
    // reset the svg    
    nc.graph.svg.selectAll('defs,g,rect').remove();
          
    // create styles for new nodes
    var newnode = '<circle id="nc-newnode" cx=0 cy=0 r=9></circle>';
    // create styles for highlighted nodes and links
    var highlights = '<style type="text/css">';
    highlights += 'line.nc-link-highlight { stroke: #000000; stroke-width: 4; } ';
    highlights += 'use.nc-node-highlight { stroke: #000000; stroke-width: 4; } ';
    highlights += '</style>';    
          
    // add ontology definitions as defs    
    var temp = $.map($.extend({}, nc.ontology.nodes, nc.ontology.links), function(value) {
        return [value];
    });    
    nc.graph.svg
    .selectAll("defs").data(temp).enter().append("defs")   
    .html(function(d) {        
        return d.defs;        
    } );
    nc.graph.svg.append("defs").html(highlights+newnode);
          
    var width = parseInt(nc.graph.svg.style("width"));    
    var height = parseInt(nc.graph.svg.style("height"));          
      
    // create new simulation    
    nc.graph.sim = d3.forceSimulation()
    .force("link", d3.forceLink().distance(45).id(function(d) {
        return d.id;
    }))    
    .force("charge", d3.forceManyBody())
    .force("center", d3.forceCenter(width / 2, height / 2))
    .velocityDecay(0.5);    
            
    // Set up panning and zoom (uses a rect to catch click-drag events)                        
    var svgpan = d3.drag().on("start", nc.graph.panstarted).
    on("drag", nc.graph.panned).on("end", nc.graph.panended);       
    var svgzoom = d3.zoom().scaleExtent([0.125, 4])   
    .on("zoom", nc.graph.zoom);  
        
    nc.graph.svg.append("rect").classed("nc-svg-background", true)
    .attr("width", "100%").attr("height", "100%")
    .style("fill", "none").style("pointer-events", "all")    
    .call(svgpan).call(svgzoom)
    .on("wheel", function() {
        d3.event.preventDefault();
    })
    .on("click", nc.graph.unselect);        
    
    // create a single group g for holding all nodes and links
    nc.graph.svg.append("g").classed("nc-svg-content", true)
    .attr("transform", "translate("+initT[0]+","+initT[1]+")scale("+initT[2]+")");                                        
            
    if (nc.graph.rawnodes.length>0) {
        nc.graph.simStart();
    }
}


/**
 * add elements into the graph svg and run the simulation
 * 
 */
nc.graph.simStart = function() {
    
    // first clear the existing svg if it already contains elements
    nc.graph.svg.selectAll("g.links,g.nodes").remove();
    nc.graph.sim.alpha(0);
    
    // filter the raw node and raw links
    nc.graph.filterNodes();
    nc.graph.filterLinks();
           
    // set up node dragging
    var nodedrag = d3.drag().on("start", nc.graph.dragstarted)
    .on("drag", nc.graph.dragged).on("end", nc.graph.dragended);
                     
    // create var with set of links (used in the tick function)
    var link = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "links")
    .selectAll("line")
    .data(nc.graph.links)
    .enter().append("line")
    .attr("class", function(d) {        
        return "nc-default-link "+d["class"];        
    })   
    .attr("id", function(d) {
        return d.id;
    })
    .on("click", nc.graph.select);                    
    
    // create a var with a set of nodes (used in the tick function)
    var node = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "nodes")
    .selectAll("use").data(nc.graph.nodes).enter().append("use")
    .attr("xlink:href", function(d) {
        return "#"+d['class'];
    })
    .attr("id", function(d) {        
        return d.id;
    })
    .attr("class",function(d) {        
        return "nc-default-node "+d["class"];        
    })
    .call(nodedrag).on("click", nc.graph.select).on("dblclick", nc.graph.unpin);        
    
    // performed at each simulation step to reposition the nodes and links
    var tick = function() {        
        link.attr("x1", dsourcex).attr("y1", dsourcey).attr("x2", dtargetx).attr("y2", dtargety);    
        node.attr("x", dx).attr("y", dy);        
    }
    
    nc.graph.sim.nodes(nc.graph.nodes).on("tick", tick);                   
    nc.graph.sim.force("link").links(nc.graph.links);          
}


/**
 * Stop the simulation
 */
nc.graph.simStop = function() {
    nc.graph.sim.stop();
}

/**
 * Start/unpause the simulation
 */
nc.graph.simUnpause = function() {
    //alert("unpausing");
    nc.graph.sim.alpha(0.7).restart();
}

var dsourcex = function(d) {
    return d.source.x;    
}
var dsourcey = function(d) {
    return d.source.y;
};
var dtargetx = function(d) {
    return d.target.x;
};
var dtargety = function(d) {
    return d.target.y;
};
var dx = function(d) {
    return d.x;
}
var dy = function(d) {
    return d.y;
}


/* ====================================================================================
 * Interactions (dragging, panning, zooming)
 * ==================================================================================== */


/**
 * Unpin the position of a node
 */
nc.graph.unpin = function(d) {    
    if ("index" in d) {
        nc.graph.nodes[d.index].fx = null;
        nc.graph.nodes[d.index].fy = null;                   
    }
}


/**
 * @param d the object (node) that is being dragged
 */
nc.graph.dragstarted = function(d) {  
    
    // pass on the event to "select" - this will highlights the dragged object
    nc.graph.select(d);
    
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.3).restart();    
            break;
        case "newlink":
            nc.graph.simStop(); 
            nc.graph.svg.select('use[id="'+d.id+'"]').classed("nc-newlink-source", true);
            nc.graph.svg.select('g[class="links"]').append("line")
            .attr('source', d.id)
            .attr('class', 'nc-draggedlink').attr("x1", d.x).attr("y1", d.y).attr("x2", d.x+20).attr("y2", d.y+20);            
            break;
        default:
            break;
    }
}

/**
 * @param d the object (node) that is being dragged 
 */
nc.graph.dragged = function(d) {     
    var point = d3.mouse(this);    
    switch (nc.graph.mode) {
        case "select":
            var dindex = d.index;                
            nc.graph.nodes[dindex].fx = point[0];
            nc.graph.nodes[dindex].fy = point[1];           
            break;
        case "newlink":
            var besttarget = nc.graph.findNearestNode(point);            
            nc.graph.svg.select('line[class="nc-draggedlink"]')
            .attr('target', besttarget.id)
            .attr("x2",besttarget.x).attr("y2", besttarget.y);            
            var newtarget = nc.graph.svg.select('#'+besttarget.id);
            if (!newtarget.classed("nc-newlink-target")) {
                nc.graph.svg.selectAll('use').classed('nc-newlink-target', false);
                newtarget.classed('nc-newlink-target', true);
            }
            break;
        default:
            break;
    }    
}

/**
 * @param d the object (node) that was dragged
 */
nc.graph.dragended = function(d) {    
    var dindex = d.index;    
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.0);
            nc.graph.nodes[dindex].fx = null;
            nc.graph.nodes[dindex].fy = null;    
            break;
        case "newlink":            
            // this is here to make links respond to drag events                        
            var newlink = nc.graph.addLink(); 
            nc.graph.select(newlink);            
            break;
        case "default":
            break;
    }        
}

// for panning it helps to keep track of the pan-start point
nc.graph.point = [0,0];
/**
 * activates when user drags the background
 */
nc.graph.panstarted = function() {   
    var p = d3.mouse(this);  
    // get original translation 
    var oldtrans = nc.graph.svg.select("g.nc-svg-content").attr("transform").split(/\(|,|\)/);    
    // record the drag start location
    nc.graph.point = [p[0]-oldtrans[1], p[1]-oldtrans[2], oldtrans[4]];   
}


/**
 * Performs the panning by adjusting the g.nc-svg-content transformation
 */
nc.graph.panned = function() {
    // compute the content transformation from the current mouse position and the 
    // drag start position
    var thispoint = d3.mouse(this);
    var diffx = thispoint[0]-nc.graph.point[0];
    var diffy = thispoint[1]-nc.graph.point[1];
    nc.graph.svg.select("g.nc-svg-content")
    .attr("transform", "translate(" + diffx +","+ diffy +")scale("+nc.graph.point[2]+")");    
}


nc.graph.panended = function() {
   
    }


/**
 * Perform rescaling on the content svg upon zoomin
 */
nc.graph.zoom = function() {        
    // get existing transformation
    var oldtrans = nc.graph.svg.select("g.nc-svg-content").attr("transform").split(/\(|,|\)/);    
    oldtrans = [parseFloat(oldtrans[1]), parseFloat(oldtrans[2]), parseFloat(oldtrans[4])];        
    var oldscale = oldtrans[2];
    // apply a scaling transformation manually
    var newscale = d3.event.transform.k;            
    var sw2 = parseInt(nc.graph.svg.style("width").replace("px",""))/2;
    var sh2 = parseInt(nc.graph.svg.style("height").replace("px",""))/2; 
    var newtrans = [sw2 + (oldtrans[0]-sw2)*(newscale/oldscale), 
    sh2+(oldtrans[1]-sh2)*(newscale/oldscale), newscale];    
    // set the new transformation into the content
    nc.graph.svg.select("g.nc-svg-content")
    .attr("transform", "translate(" + newtrans[0] +","+ newtrans[1] +")scale("+newtrans[2]+")")        
}


/* ====================================================================================
 * Helper functions
 * ==================================================================================== */


/**
 * Converts between a mouse coordinate p to an avg coordinate 
 * (The differences comes from transformations and scaling)
 */
nc.graph.getCoord = function(p) {        
    var trans = nc.graph.getTransformation();
    return [(p[0]-trans[0])/trans[2], (p[1]-trans[1])/trans[2]];
}

/**
 * Make the new link/new node forms look normal
 */
nc.graph.resetForms = function() {
    $('#fg-linkname,#fg-linktitle,#fg-linkclass,#fg-linksource,#fg-linktarget').removeClass('has-warning has-error has-success');
    $('#fg-linkname label').html("Link name:");
    $('#fg-linktitle label').html("Link title:");
    $('#fg-linkclass label').html("Link class:");
    $('#fg-linksource label').html("Source:");
    $('#fg-linktarget label').html("Target:");
    $('#fg-nodename,#fg-nodetitle,#fg-nodeclass').removeClass('has-warning has-error has-success');   
    $('#fg-nodename label').html("Node name");
    $('#fg-nodetitle label').html("Node title");
    $('#fg-nodeclass label').html("Node class");       
}

/**
 * Find the node that is nearest to the given point
 */
nc.graph.findNearestNode = function(p) {
    var bestid = 0;
    var bestd = Infinity;
    for (var i=0; i<nc.graph.nodes.length; i++) {
        var nowd = Math.pow(nc.graph.nodes[i].x - p[0], 2) + Math.pow(nc.graph.nodes[i].y - p[1], 2);
        if (nowd<bestd) {
            bestd = nowd;
            bestid = i;
        }
    }
    return nc.graph.nodes[bestid];
}


/**
 * Replace an existing node id by a new one.
 * Returns the index of the node in the nc.graph.nodes array
 */
nc.graph.replaceNodeId = function(oldid, newid) {
    
    var i;
    // replace any linking data
    var llen = nc.graph.links.length;
    for (i=0; i<llen; i++) {
        if (nc.graph.links[i].source==oldid) {
            nc.graph.links[i].source = newid;
        }
        if (nc.graph.links[i].target==oldid) {
            nc.graph.links[i].target = newid;
        }
    }
    // replace the id in the nodes array
    var nlen = nc.graph.nodes.length;
    for (i=0; i<nlen; i++) {
        if (nc.graph.nodes[i].id==oldid) {
            nc.graph.nodes[i].id = newid;
            return i;
        }
    }
    
    return -1;
}


/**
 * Replaces an id in the nc.graph.links array
 * Returns the index to the link in question
 */
nc.graph.replaceLinkId = function(oldid, newid) {     
    // replace any linking data
    var llen = nc.graph.links.length;
    for (var i=0; i<llen; i++) {
        if (nc.graph.links[i].id==oldid) {
            nc.graph.links[i].id = newid;
            return i;
        }        
    }
    return -1;
}

/* ====================================================================================
 * Communicating with server
 * ==================================================================================== */


/**
 * Processes the new node form submit action
 */
nc.graph.createNode = function() {
    
    nc.graph.resetForms();        
   
    // check if the user is allowed to create users
    if (!nc.editor) {
        return;
    }
   
    // basic checks on the text boxes    
    if (nc.utils.checkFormInput('fg-nodename', "node name", 1)+
        nc.utils.checkFormInput('fg-nodetitle', "node title", 2) < 2) return 0;    
   
    var oldid = $('#nc-graph-newnode form').attr('val');
    var newname = $('#fg-nodename input').val();
    var newclass = $('#fg-nodeclass button.dropdown-toggle').attr('selection');
   
    // give feedback on the form that a request is being sent
    $('#nc-graph-newnode button.submit')
    .removeClass('btn-success').addClass("btn-default")
    .html("Sending...").attr("disabled", true); 
   
    // send a request to create node
    // post the registration request 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "createNewNode", 
        network: nc.network,
        name: newname,
        title: $('#fg-nodetitle input').val(),
        'abstract': newname,
        'content': newname,
        'class': newclass
    }, function(data) {          
        nc.utils.alert(data);                
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                $('#fg-nodename').addClass('has-error has-feedback');                
                $('#fg-nodename label').html("Please choose another node name:");                
            } else if (data['success']==true) {                                                                 
                $('#nc-graph-newnode form').attr("disabled", true);    
                $('#nc-graph-newnode button.submit').attr("disabled", true);    
                // replace the node id in from the temporary one to the real one
                var nodeindex = nc.graph.replaceNodeId(oldid, data['data']);
                nc.graph.nodes[nodeindex].name = newname;
                nc.graph.nodes[nodeindex]["class"] = newclass;
                nc.graph.svg.select('use[id="'+oldid+'"]')
                .attr("id", data['data'])
                .classed('nc-newnode', false).classed('nc-node-highlight', false).
                classed(newclass, true).classed('nc-node-highlight', true); 
                $('#nc-graph-newnode').hide();
            }
        } 
        
        // make user wait a little before next attempt
        setTimeout(function() {
            $('#nc-graph-newnode form').attr("disabled", false);
            $('#nc-graph-newnode button.submit')
            .addClass('btn-success').removeClass("btn-default disabled").html("Create node")
            .attr("disabled", false);
        }, nc.ui.timeout/4);
    }

    );    
}

/**
 * * Processes the new link form submit action
 */
nc.graph.createLink = function() {
    
    nc.graph.resetForms();
   
    // check if user has permissions for the action
    if (!nc.editor) {
        return;
    }
   
    // basic checks on the text boxes    
    if (nc.utils.checkFormInput('fg-linkname', "link name", 1) +
        nc.utils.checkFormInput('fg-linksource', "link source", 1) +
        nc.utils.checkFormInput('fg-linktarget', "link target", 1) +        
        nc.utils.checkFormInput('fg-linktitle', "link title", 2) < 4) return 0;    
    
    // fetch from form into variables here
    var oldid = $('#nc-graph-newlink form').attr('val');
    var newname = $('#fg-linkname input').val();    
    var newclass = $('#fg-linkclass button.dropdown-toggle').attr('selection');
   
    // give feedback on the form that a request is being sent
    $('#nc-graph-newlink button.submit')
    .removeClass('btn-success').addClass("btn-default")
    .html("Sending...").attr("disabled", true); 
   
    // send a request to create node
    // post the registration request 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "createNewLink", 
        network: nc.network,
        name: newname,
        title: $('#fg-linktitle input').val(),
        'abstract': newname,
        'content': newname,
        'class': newclass,
        source: $('#fg-linksource input').val(),
        target: $('#fg-linktarget input').val()
    }, function(data) {           
        nc.utils.alert(data);                
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                $('#fg-linkname').addClass('has-error has-feedback');                
                $('#fg-linkname label').html("Please choose another link name:");                
            } else if (data['success']==true) {                 
                $('#nc-graph-newlink form').attr("disabled", true);    
                $('#nc-graph-newlink button.submit').attr("disabled", true);    
                // replace the node id in from the temporary one to the real one
                var linkindex = nc.graph.replaceLinkId(oldid, data['data']);
                nc.graph.links[linkindex].name = newname;
                nc.graph.links[linkindex]["class"] = newclass;
                nc.graph.svg.select('line[id="'+oldid+'"]')
                .attr("id", data['data'])
                .classed('nc-newlink', false).classed('nc-link-highlight', false).
                classed(newclass, true).classed('nc-link-highlight', true);    
                $('#nc-graph-newlink').hide();            
            }
        }                 
        // make user wait a little before next attempt
        setTimeout(function() {
            $('#nc-graph-newlink form').attr("disabled", false);
            $('#nc-graph-newlink button.submit')
            .addClass('btn-success').removeClass("btn-default disabled").html("Create link")
            .attr("disabled", false);              
        }, nc.ui.timeout/4);
            
    }
    );
   
    
}



/**
 * remove a node from the viz (not from the server)
 */
nc.graph.removeNode = function() {    
    // pause the simulation just in case
    nc.graph.simStop();
    
    // identify the selected node        
    var nodeid = nc.graph.svg.select("use.nc-node-highlight").attr("id");
         
    // get rid of the node id from the array    
    nc.graph.rawnodes = nc.graph.rawnodes.filter(function(value, index, array) {
        return (value.id!=nodeid);
    });    
    // for links, first reset the source and target to simple labels (not arrays)
    // then remove the links that point to the node
    for (var i=0; i<nc.graph.rawlinks.length; i++) {
        nc.graph.rawlinks[i].source = nc.graph.rawlinks[i].source.id;
        nc.graph.rawlinks[i].target = nc.graph.rawlinks[i].target.id;
    }    
    nc.graph.rawlinks = nc.graph.rawlinks.filter(function(value, index, array) {
        return (value.source!=nodeid && value.target!=nodeid); 
    });
        
    // restart the simulation
    nc.graph.initSimulation();
    
    $('#nc-graph-newnode').hide();
}


/**
 * remove a highlighted link from the viz
 */
nc.graph.removeLink = function() {    
    // pause the simulation just in case
    nc.graph.simStop();
    
    // identify the selected link        
    var linkid = nc.graph.svg.select("line.nc-link-highlight").attr("id");
             
    // remove the link with that id
    nc.graph.rawlinks = nc.graph.rawlinks.filter(function(value, index, array) {
        return (value.id!=linkid); 
    });
        
    // restart the simulation
    nc.graph.initSimulation();
    $('#nc-graph-newlink').hide();
}

/* 
 * nc-ontology.js
 * 
 * 
 */


if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.ontology = {};
nc.ontology.nodes = {};
nc.ontology.links = {};


/* ====================================================================================
 * Building/Managing ontology structures
 * ==================================================================================== */

/**
 * Invoked when user wants to create a new class for a node or link
 */
nc.ontology.createClass = function(classname, islink, isdirectional) {
    
    // check the class name string     
    if (nc.utils.checkString(classname, 1)<1) {
        nc.msg("Hey!", "Invalid class name");
        return;
    }
    
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "createNewClass", 
        network: nc.network,
        name: classname,
        title: classname,
        'abstract': '',
        content: '',
        parent: '',  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {
        nc.utils.alert(data);      
        data = JSON.parse(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {                
            // insert was successful, so append the tree   
            var thisdefs = '<circle id="'+classname+'" cx=0 cy=0 r=9 />';
            if (islink) {
                thisdefs = '<style type="text/css"></style>';
            }
            var newrow = {
                class_id:data['data'], 
                parent_id:'', 
                connector:+islink,
                directional:+isdirectional, 
                name:classname,
                defs: thisdefs,
                status: 1
            };
            nc.ui.addClassTreeRow(newrow, 1);                
        }
    });  
}

/**
 * Send a request to update a class name or parent structure
 */
nc.ontology.updateClassProperties = function(classid, classname, parentid, islink, isdirectional) {
                
    if (nc.utils.checkString(classname, 1)<1) {
        nc.msg("Hey!", "Invalid class name");
        return;
    }
                
    // must translate between classid and targetname 
    // also between parentid and parentname
    var targetname = $('div.nc-classdisplay[val="'+classid+'"] span[val="nc-classname"]').html();   
    var parentname = parentid;
    if (parentid!='') {
        parentname = $('div.nc-classdisplay[val="'+parentid+'"] span[val="nc-classname"]').html();
    }
    
    // get the svg style from the page
    var thisdefs = nc.utils.sanitize($('form[val="'+classid+'"] textarea').val(), true);    
              
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "updateClass", 
        network: nc.network,
        target: targetname,
        name: classname,
        title: '',
        'abstract': '',
        content: '',
        defs: thisdefs,
        status: 1,
        parent: parentname,  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {             
        nc.utils.alert(data);      
        data = JSON.parse(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {   
            // update the tree display
            var targetdisplay = $('div.nc-classdisplay[val="'+classid+'"]');            
            targetdisplay.find('> span.nc-comment[val="nc-classname"]').html(classname);          
            var dirtext = (isdirectional ? ' (directional)': ''); 
            targetdisplay.find('> span.nc-comment[val="nc-directional"]').html(dirtext);
        }
    });  
}

/**
 * Preps to send request to remove/disactivate/deprecate a class 
 * As this is an important step, this function shows a modal to confirm
 * The action is only performed upon confirmation
 */ 
nc.ontology.askConfirmation = function(classid, action) {   
    var classname = $('li[val="'+classid+'"] span[val="nc-classname"]').html();
    var modal = $('#nc-deprecateconfirm-modal');
    modal.find('#nc-deprecateconfirm-action').html(action);
    modal.find('#nc-deprecateconfirm-class').html(classname).attr("val", classid);        
    if (action=="inactive") {
        $('#nc-deprecateconfirm-modal button[val="nc-confirm"]')
        .off("click").on("click", nc.ontology.confirmDeprecate);
        modal.find('p[val="deprecate"]').show();
        modal.find('p[val="activate"]').hide();
    } else {
        $('#nc-deprecateconfirm-modal button[val="nc-confirm"]')
        .off("click").on("click", nc.ontology.confirmActivate);
        modal.find('p[val="deprecate"]').hide();
        modal.find('p[val="activate"]').show();
    }
    modal.modal("show");    
}


/*
 * Sends a request to activate a class
 */
nc.ontology.confirmActivate = function() {
    // get the classid from the modal that called this function
    var infoobj = $('#nc-deprecateconfirm-modal #nc-deprecateconfirm-class');
    var classid = infoobj.attr("val");            
    var classname = infoobj.html();      
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "activateClass", 
        network: nc.network,
        name: classname
    }, function(data) {
        nc.utils.alert(data);              
        data = JSON.parse(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {            
            if (data['data']==true) {                
                nc.ui.toggleClassDisplay($('li[val="'+classid+'"]'));
            } else {
                nc.msg("That's strange", data['data']);                
            }
        }
    });  
}

/**
 * Sends a request to deprecate/remova a class
 */
nc.ontology.confirmDeprecate = function() {
    // fetch the confirmed class id from the modal that callled this function
    var infoobj = $('#nc-deprecateconfirm-modal #nc-deprecateconfirm-class');
    var classid = infoobj.attr("val");            
    var classname = infoobj.html();  
               
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "removeClass", 
        network: nc.network,
        name: classname
    }, function(data) {
        nc.utils.alert(data);              
        data = JSON.parse(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {            
            var thisrow = $('li[val="'+classid+'"]');            
            if (data['data']==true) {
                // the class has been truly removed
                thisrow.fadeOut(nc.ui.speed, function() {
                    $(this).remove()
                } ); 
            } else {
                // the class has been deprecated
                nc.ui.toggleClassDisplay(thisrow);
            }
        }
    });  
}


/**
 * gives an object with class data, adds a fields to each class type with a long
 * class name that includes the hierarchy.  
 * 
 * @param ontolist string 'nodes' or 'links'
 * 
 */
nc.ontology.addLongnames = function(ontolist) {
     
    var tree = $('<div><div val="" text=""></div></div>');
    
    var counter = 0;
    var targetcount = Object.keys(ontolist).length;
    while (counter<targetcount) {        
        counter=0;
        $.each(ontolist, function(key, val){        
            var thisid = val['class_id'],
            thisname = val['name'],
            thisparent = val['parent_id'],
            longname = thisname;
        
            if (tree.find('div[val="'+thisid+'"]').length==1) {
                counter++;
            } else {
                var parentdiv = tree.find('div[val="'+thisparent+'"]')
                if (parentdiv.length==1) {                    
                    if (thisparent!="") {
                        longname = parentdiv.attr('text')+":"+thisname;
                    }
                    parentdiv.append('<div val="'+thisid+'" text="'+longname+'"></div>');
                    ontolist[thisid].longname = longname;
                    counter++;
                } 
            }
        });       
    }

    return ontolist;            
}
/* 
 * nc-sandbox.js
 * 
 * Interactive md and mdalive sandboxes
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.sandbox = {};


/* ====================================================================================
 * 
 * ==================================================================================== */

/**
 * Invoked when user changes a form element.
 * 
 * This scans all form elements and generated a json object. Pastes this json object
 * into the markdown box
 */
nc.sandbox.generateMarkdown = function() {    
    var req = $('#nc-sandbox-required');
    var opt = $('#nc-sandbox-optional');
    
    // start building an object
    var result = {};
        
    // conversion function transfers data from a form group to result {}   
    var fg2obj = function() {
        var iname = $(this).attr("val");       
        var iinput = $(this).find("input");   
        var now = "";
        if (iinput.length==1) {           
            now = iinput.val();              
            result[iname] = (isNaN(now) || now=='' ? now : +now);            
        } else if (iinput.length>1) {            
            result[iname] = [];
            iinput.each( function() {
                var now = $(this).val();
                now = (isNaN(now) || now=='' ? now : +now);
                result[iname].push(now);  
            });
        } else {            
            var xx = $(this).find("textarea").val();            
            if (xx!="") {                
                var data = xx.split("\n");                
                var colnames = ($(this).attr("colnames")).split(" ");                                
                result[iname] = [];
                for (var i=0; i<data.length; i++) {
                    if (data[i]=='') {
                        data[i]=' \t';
                    }
                    var dd = {};
                    data[i] = data[i].split("\t");
                    for (var j=0; j<data[i].length; j++) {
                        now = data[i][j];
                        now = (isNaN(now) || now=='' || now==' ' ? now : +now);
                        dd[colnames[j]] = now;
                    }
                    result[iname].push(dd);
                }
            }
        }
    }
        
    req.find('.form-group').each( fg2obj);
    opt.find('.form-group').each( fg2obj);
            
    var mdout = '```mdalive '+req.attr("val")+'\n';
    mdout+=JSON.stringify(result, null, 2)+'\n';
    mdout += '```';
    
    $('#nc-sandbox-md').val(mdout).change();    
}


/* ====================================================================================
 * On page load, activate sandbox converter listener
 * ==================================================================================== */

/**
 * This starts the page initialization code
 */
$(document).ready(
    function () {        
        var sandout = $('#nc-sandbox-preview');
        if (sandout.length<=0) {
            return;
        }
        // code that will generate the preview
        var sandmd = $('#nc-sandbox-md');       
        $("#nc-sandbox-md").on('change keyup paste input', function(){
            var textmd = sandmd.val();
            sandout.html(nc.utils.md2html(textmd));      
        });        
        // gui toggling
        var sandopt = $('#nc-sandbox-optional').hide();
        $('.nc-sandbox-optional').css("cursor", "pointer").click(function() { 
            sandopt.toggle();
            $(this).find('span').toggleClass("dropup");
        });
    
        // using two spaces for tabs in textarea
        $('textarea').on('keyup', function(e) {            
            if (e.keyCode==32) {
                var nowval = $(this).val();
                var nowpos = $(this).prop("selectionStart");                   
                if (nowval.substr(nowpos-2, 2)=='  ') {                    
                    $(this).val(nowval.substr(0, nowpos-2)+"\t"+nowval.substr(nowpos));
                    $(this).prop("selectionStart", nowpos-1).prop("selectionEnd", nowpos-1);
                }                
            }
        });
    
        // generate markdown from the forms
        $('.form-group input, .form-group textarea').on("keyup paste input", nc.sandbox.generateMarkdown);    
            
    });   
   
/* 
 * nc-ui.js
 * 
 * Functions that generate ui elements/widgets. 
 * In some cases the widgets come with events/methods. 
 * 
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.ui = {};


/* ====================================================================================
 * Constants that determine user experience
 * ==================================================================================== */

// speed determines animations like fade-outs
nc.ui.speed = 500;
nc.ui.fast = 200;
// timeout used when making a user wait on purpose 
// (e.g. between updates or to temporarily indicate the output of an action)
nc.ui.timeout = 2000;


/* ====================================================================================
 * Permissions
 * ==================================================================================== */

/**
 * create html with a form for updating user permissions
 *  
 * @param udata - json encoded array, each element should be an object describing 
 * one user
 */
nc.ui.PermissionsWidget = function(udata) {
            
    // internal function for making a button
    // val - 0-3 determines label on the button
    // uid - the user id (used to disactivate some buttons for the guest user)
    // perm - 0-3 permission level for this user
    // returns an <label></label> object
    function ncuiPB(val, uid, perm) {
        // convert a numeric value into a label        
        var vl = ['None', 'View', 'Comment', 'Edit', 'Curate'];
        var lab = vl[val];    
        perm = (perm==val ? ' active' : '');        
        if (uid=='guest' && val>1) perm = ' disabled';            
                
        // create a radio button html
        var html = '<label class="btn btn-default btn-sm nc-mw-sm'+perm+'">';
        html += '<input type="radio" autocomplete="off" value="'+val+'" '+perm+'>';
        html += lab+'</label>';        
        return html;
    }
         
    var ans = $('<div></div>');
    $.each(udata, function(key, val){ 
        
        var uid = val['user_id'];        
        var up = val['permissions'];        
        var nowlab = uid;                
        if (uid != "guest") {            
            nowlab += " ("+val['user_firstname']+" "+val['user_middlename']+" "+val['user_lastname']+")";
        }
        
        // structure will be form > form-group with (label, btn-group, btn)
        var html = '<form class="form-inline nc-form-permissions" val="'+uid+'" onsubmit="return false;">';        
        html += '<div class="form-group" style="width:100%">';
        html += '<label class="col-form-label nc-fg-name">'+nowlab+'</label>';
        html += '<span><div class="btn-group" data-toggle="buttons">';                
        for (var pp=0; pp<5; pp++) 
            html += ncuiPB(pp, uid, up);   
        html += '</div>'; // closes the btn-group
        html += '<button class="btn btn-success btn-sm" val="'+uid+'">Update</button></span>';        
        html += '</div></form>';                
        
        html = $(html);
        html.find('button').click(function() { 
            nc.users.updatePermissions(uid); 
        });
        
        // append to the main answer
        ans.append(html);
    });        
                
    return ans;                            
}
      

/* ====================================================================================
 * Ontology
 * ==================================================================================== */

/**
 * create a bit of html with a form updating user permissions
 *  
 * @param classdata - array with existing class structure
 * @param islink - boolean (true to populate node class tree, false to populate link tree) 
 * 
 */
nc.ui.ClassTreeWidget = function(classdata, islink) {
                    
    // get the root div for the treee
    var root = (islink ? $('#nc-ontology-links'): $('#nc-ontology-nodes'));                         
          
    // create a div for children and a new form
    var rootrow = {
        parent_id:'',
        class_id:'',
        name:'', 
        connector:+islink, 
        directional:0
    };    
    var parentsofroot = '<ol class="nc-classtree-children nc-classtree-root" val=""></ol>';     
    parentsofroot += nc.ui.ClassForm(rootrow);    
    root.append(parentsofroot);
        
    // set up drag-drop of classes (uses jquery extension "sortable")  
    var oldContainer;
    root.find(".nc-classtree-children").sortable({
        //handle: 'button[val="move"]',
        handle: 'svg',
        afterMove: function (placeholder, container) {
            if(oldContainer != container){
                if(oldContainer)
                    oldContainer.el.removeClass("droptarget");
                container.el.addClass("droptarget");
                oldContainer = container;                                       
            }
        },
        onDrop: function ($item, container, _super) {
            container.el.removeClass("droptarget");            
            _super($item, container);      
            $item.addClass('aftermove');
            setTimeout(function() {
                $item.removeClass('aftermove');
            }, nc.ui.timeout/2);
        }
    });
     
    // populate the ontology tree
    // uses multiple passes to make all classes display regardless of the order
    // in which they appear in the array    
    do {
        var addagain = 0;
        $.each(classdata, function(key, val){                                        
            addagain += nc.ui.addClassTreeRow(val);          
        });        
    } while (addagain<Object.keys(classdata).length);
                
    // create functions that respond to events on the tree
    // clicking a class name to see the summary page
    root.on("click", 'span[val="nc-classname"]', function() {
        var classid = $(this).parent().attr("val");
        window.location.replace("?network="+nc.network+"&object="+classid);
    });
    // submitting a new class
    root.on("submit", "form.nc-classcreate", function() {                
        var newclassname = $(this).find("input").val();            
        var isdirectional = +$(this).find("input.form-check-input").is(":checked");        
        nc.ontology.createClass(newclassname, islink, isdirectional); 
    });  
    // clicking to edit an existing class
    root.on("click", "div.nc-classdisplay button[val='edit']", function() {                        
        var classid = $(this).parent().attr('val');
        // make sure input box shows current classname
        var classname = $(this).parent().find('span.nc-comment[val="nc-classname"]').html();        
        var thisform = root.find("form.nc-classupdate[val='"+classid+"']");
        thisform.find('input').val(classname);
        // toggle visibility of display/form
        thisform.toggle();                
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();        
        root.find('div.nc-style').show(nc.ui.fast);
    });    
    // clicking to remove/deprecate a class
    root.on("click", "div.nc-classdisplay button[val='remove']", function() {                                
        var classid = $(this).parent().attr('val');                      
        nc.ontology.askConfirmation(classid, 'inactive');
    });
    // clicking to remove/deprecate a class
    root.on("click", "div.nc-classdisplay button[val='activate']", function() {                                
        var classid = $(this).parent().attr('val');                      
        nc.ontology.askConfirmation(classid, 'active');
    });
    // clicking to cancel updating an existing class
    root.on("click", "form.nc-classupdate .nc-btn-class-cancel", function() {                                
        var classid = $(this).attr('val');           
        root.find('div.nc-style').hide(nc.ui.fast);        
        setTimeout(function() {           
            root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
            root.find("form.nc-classupdate[val='"+classid+"']").toggle();                            
        }, nc.ui.fast);        
    });
    // clicking to update the value of an existing class
    root.on("click", "form.nc-classupdate .nc-btn-class-update", function() {        
        var thisform = $(this).parent().parent();
        var classid=thisform.attr('val');
        var parentid = thisform.parent().parent().attr('val');        
        var newclassname = thisform.find("input").val();            
        var islink = thisform.find("input.form-check-input").length>0;
        var isdirectional = 0+thisform.find("input.form-check-input").is(":checked");              
        nc.ontology.updateClassProperties(classid, newclassname, parentid, islink, isdirectional);        
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
        root.find("form.nc-classupdate[val='"+classid+"']").toggle();                
    });    
    // updating the svg textarea
    // on had "change keyup paste input"
    root.on("change keyup", "textarea", function() {
        //alert("in here");                
        var textsvg = nc.utils.sanitize($(this).val(), true);                         
        // find the svg component        
        var thisform  = $(this).parent().parent().parent();
        var thisid = thisform.attr('val');
        try {
            thisform.find('svg[val="'+thisid+'"] defs').empty().append($(textsvg));
            thisform.find('svg[val="'+thisid+'"]').DOMRefresh();
        } catch(err) {
        // don't do anything with the error, just wait for better input
        }
    });
    
    // after all the tree is populated, display a subset of the created elements 
    // (others will become activated upon editing of the class)
    root.find("div.nc-classdisplay").show();    
    root.find("form.nc-classcreate[val='']").show();    
}



/**
 * Creates one row in a class tree
 * Row consists of a div with a label and a div below that will hold children
 * 
 * @param classrow - array with components class_id, etc. (see ClassForm)
 */
nc.ui.ClassTreeRowWidget = function(classrow) {
    
    // create objects for displaying, editing, and adding subclasses
    var adisplay = nc.ui.ClassDisplay(classrow);    
    var aform = nc.ui.ClassForm(classrow);
    var cid = classrow['class_id'];    
    var cname = classrow['name'];
    var achildren = '<ol class="nc-classtree-children" val="'+classrow['class_id']+'"></ol>';       
    var classvg = '<svg class="nc-symbol" val="'+cid+'"><defs></defs>';
    classvg += '<g transform="translate(18,18)">';
    if (classrow['connector']==1) {
        classvg+='<line class="nc-default-link '+cname+'" x1=-17 x2=17 y1=0 y2=0/>';
    } else {
        classvg+='<use xlink:href="#'+cname+'" class="nc-default-node '+cname+'"/>';
    }    
    classvg+='</g></svg>';
    
    // create the widget from the components
    var obj= $('<li val="'+classrow['class_id']+'">'+classvg+ adisplay + aform + achildren+'</li>');                

    // modify the object if class is inactive
    if (classrow['status']!=1) {
        obj = nc.ui.toggleClassDisplay(obj);
    }    
    
    return obj;
}


/**
 * Toggle between deprecated and active ontology class (visual)
 * 
 * The implementation looks like it can be done with ".toggle()" but the first time 
 * 
 * @param obj - a jquery object holding the <li> for the class
 * 
 */
nc.ui.toggleClassDisplay = function(obj) {     
    obj.find('> div.nc-classdisplay, > svg')
    .toggleClass("nc-deprecated")
    .find('button,span.nc-comment[val="nc-deprecated"]').toggle();            
    return obj;
}

/**
 * Creates html displays one row in the classtree (when viewing only)
 * 
 * @param classrow - array with details on this class 
 * 
 */
nc.ui.ClassDisplay = function(classrow) {
    
    // create a div with one label (possible a directional comment) and one button
    var fg = '<div val="'+classrow['class_id']+'" class="nc-classdisplay">';     
    fg += '<span class="nc-comment" val="nc-classname">'+classrow['name']+'</span>';
    // forms for links include a checkbox for directional links    
    fg += '<span class="nc-comment" val="nc-directional">';
    if (+classrow['directional']) fg+= ' (directional)';    
    fg += '</span><span class="nc-comment" val="nc-deprecated" style="display: none">[inactive]</span>';    
    if (nc.curator) { 
        var temp = '<button class="pull-right btn btn-primary btn-sm nc-mw-sm nc-hm-3" ';
        fg += temp +' val="remove">Remove</button>';
        fg += temp +' val="activate" style="display: none">Activate</button>';                       
        fg += temp + ' val="edit">Edit</button>';   
    //fg += temp + ' val="move">Move</button>';               
    }
    fg += '</div>'; 
                
    return fg;
}

/**
 * Creates a form. The form is slightly different depending on whether the classrow
 * has an exisitng 'classname. 
 * 
 * @param classrow
 * 
 * array with settings describing a class
 * 
 * if element 'classname' is non-empty, creates a class update form
 * if element 'classname' is empty, creates a new class form
 * 
 */
nc.ui.ClassForm = function(classrow) {
    
    if (!nc.curator) {
        return "";
    }
    
    var classid = classrow['class_id'];    
    var classname = classrow['name'];
    var classdefs = classrow['defs'];
    var islink = +classrow['connector'];
    var directional = +classrow['directional'];
     
    var formclass = (classname=='' ? 'nc-classcreate' : 'nc-classupdate');    
    
    // create the form
    var ff ='<form val="'+classid+'" style="display: none" class="form-inline nc-class-form '+formclass+'" onsubmit="return false">';
    // create the textbox asking for the classname
    var fg = '<div class="form-group"><input class="form-control input-sm" placeholder="Class name"';
    if (classname!='') fg+= ' val="'+classname+'"';    
    fg += '></div>';        
    // forms for links include a checkbox for directional links
    if (islink) {        
        fg += '<div class="form-group"><label class="form-check-inline"><input type="checkbox" class="form-check-input"';
        if (directional) fg+= ' checked';        
        fg+='>Directional</label></div>';        
    }
    // buttons to create a new class or update a class name
    if (classname=='') {
        fg += '<div class="form-group"><button class="btn btn-success btn-sm submit">';
        fg += 'Create new class</button></div>';                       
    } else {
        fg += '<div class="form-group">';
        fg+= '<button val="'+classid+'" class="btn btn-primary btn-sm nc-btn-class-update">Update</button>';
        fg+= '<button val="'+classid+'" class="btn btn-primary btn-sm nc-btn-class-cancel">Cancel</button>';
        fg += '</div><br/>';
        fg += '<div class="form-group nc-style"><textarea class="form-control" rows=4>'+classdefs+'</textarea></div>';
    }
    var ff2 = '</div></form>';
              
    return ff+fg+ff2;    
}


/**
 * Create a row in a class tree and insert it into the page
 * 
 * @return boolean
 * 
 * true if the row was successfully insert
 * false if not (e.g. if the attempt is made before the parent is in the dom)
 */
nc.ui.addClassTreeRow = function(classrow) {  
                  
    // find the root of the relevant tree  
    var root = (+classrow['connector'] ? $('#nc-ontology-links'): $('#nc-ontology-nodes'));
       
    // check if this class already exists
    if (root.find('li[val="'+classrow['class_id']+'"]').length>0) return true;        
       
    // find the target div where to insert the node    
    var parentid = classrow['parent_id'];        
    var targetdiv = root.find('ol.nc-classtree-children[val="'+parentid+'"]');            
    if (targetdiv.length==0) return false;        
    
    // create the widget for this class
    var newobj = $(nc.ui.ClassTreeRowWidget(classrow)).hide();    
    
    // figure out whether to insert before the form or at the end    
    if (targetdiv.children("form.nc-classcreate").length > 0) {         
        $(newobj).insertBefore(targetdiv.children('form.nc-classcreate'));
    } else {               
        targetdiv.append(newobj);
    }   
    targetdiv.find("li").show(nc.ui.speed);
    targetdiv.find("div.nc-classdisplay").show(nc.ui.speed);           
    
    // for some reason I don't understand, the svg generation doesn't appear if
    // the handler is called straigh-away, but it works after a short delay...
    setTimeout(function() {
        targetdiv.find('textarea').keyup();
    }, nc.ui.speed);
           
    
    return true;
}



/* ====================================================================================
 * Log
 * ==================================================================================== */

/**
 * Create a toolbar for the activity log
 */
nc.ui.ActivityLogToolbar = function(logsize, pagelen) {    
    var html = '<ul class="pagination">';
    var numpages = logsize/pagelen;
    
    for (var i=0; i<numpages; i++) {
        html += '<li value='+i+'><a href="javascript:nc.loadActivity('+i+', '+pagelen+')">'+(i+1)+'</a></li>';
    }
    html += '</ul><div id="nc-log-contents"></div>';
     
    return(html);     
}

/**
 * @param data - an array of arrays
 * each element in array should hold data on one row in the log table
 */
nc.ui.populateActivityArea = function(data) {
    var ans = '';
    $.each(data, function(key, val){        
        ans += nc.ui.OneLogEntry(val);
    });    
    $('#nc-log-contents').html(ans);
}

/**
 * Provides html to write out one line in the log table
 */
nc.ui.OneLogEntry = function(data) {
    var html = '<div class="media nc-log-entry">';      
    html += '<span class="nc-log-entry-date">'+data['datetime']+' </span>';
    html+= '<span class="nc-log-entry-user">'+data['user_id']+' </span>';
    html += '<span class="nc-log-entry-action">'+data['action']+' </span>';
    html += '<span class="nc-log-entry-target">'+data['target_name']+' </span>';
    if (data['value'].length>0) {
        html += '<span class="nc-log-entry-value">('+data['value']+')</span>';
    }
    html += '</div>';        
    return html;    
}



/* ====================================================================================
 * Generic, i.e. small-scale widgets
 * ==================================================================================== */

/**
 * Create a toolbar button group
 * 
 * @param aa array of strings to place in the button group
 */
nc.ui.ButtonGroup = function(aa) {
    
    var html = '<div class="btn-group nc-toolbar-group nc-toolbar-group-new" role="group">';
    for (var i in aa) {
        html += '<button class="btn btn-primary" val="'+aa[i]+'">'+aa[i]+'</button>';
    }
    html += '</div>';
    
    return $(html);
}

/**
 * Create a button with a dropdown list
 * 
 * @param atype string prefix 
 * @param aa array, each element is assumed to contain a label and val
 * @param aval string placed in button val field 
 * (use this to distinguish one dropdown from another) 
 * @param withdeprecated, boolean, whether to include items with status!=1
 * 
 */
nc.ui.DropdownButton=function(atype, aa, aval, withdeprecated) {
        
    var caret = '<span class="pull-right caret nc-dropdown-caret"></span>';
    
    var html = '<div class="btn-group nc-toolbar-group nc-toolbar-group-new" role="group">';    
    html += '<div class="btn-group" role="group">';        
    html += '<button class="btn btn-primary dropdown-toggle" val="'+aval+'" class_name="" data-toggle="dropdown"><span class="pull-left nc-classname-span">'+atype+' '+'</span>'+caret+'</button>';  
    html += '<ul class="dropdown-menu">';
    for (var i in aa) {        
        var iinclude = true, iclass= "";
        if (aa[i].status!=1) { 
            if (!withdeprecated) {
                iinclude = false;
            } else {
                iclass = ' class="nc-deprecated"';
            }            
        }        
        if (iinclude) {
            html += '<li'+iclass+'><a class_id="'+aa[i].id+'" class_name="'+aa[i].val+'" href="#">' + aa[i].label + '</a></li>'
        }
    }
    html += '</ul>';
    html += '</div></div>'; // this closes dropdown and btn-group
    
    // create object
    var dropb = $(html);
    
    // attach handlers for the dropdown links
    dropb.find("a").click(function() {
        // find the text and add it        
        var nowid = $(this).attr("class_id");
        var nowname = $(this).attr("class_name");
        //alert(nowid+" "+nowname);
        var p4 = $(this).parent().parent().parent().parent();
        p4.find('button.dropdown-toggle').html('<span class="pull-left nc-classname-span">'+atype+' '+nowname +'</span>'+ caret)
        .addClass('active').attr("class_name", nowname).attr("class_id", nowid); 
        $(this).dropdown("toggle");        
        return false;
    });       
    
    return dropb;
}



/* ====================================================================================
 * Curation
 * ==================================================================================== */

/**
 * Create a div with a toolbox for curation/editing
 * This is used to show/edit page elements (e.g. abstacts) as well as bodies
 * of comments.
 *
 *
 */
nc.ui.AnnoEditBox = function() {
        
    // write static html to define components of the toolbox
    var html = '<div class="nc-curation-box">';
    html += '<div class="nc-curation-toolbox" style="display: none">';
    html += '<a role="button" class="nc-curation-toolbox-md btn btn-sm btn-default nc-mw-sm" style="display: none">Edit</a>';
    html += '<a role="button" class="nc-curation-toolbox-preview btn btn-sm btn-default nc-mw-sm">Preview</a>';    
    html += '<a role="button" class="nc-curation-toolbox-close pull-right">close</a>';
    html += '</div><div class="nc-curation-content"></div>';
    html += '<textarea class="nc-curation-content" style="display: none"></textarea>';
    html += '<a role="button" class="btn btn-sm btn-success nc-submit nc-mw-sm" style="display: none">Submit</a>';
    html += '<a role="button" class="btn btn-sm btn-danger nc-remove nc-mw-sm" style="display: none">Remove</a></div>';    
        
    // create DOM objects, then add actions to the toolbox buttons
    var curabox = $(html);        

    // clicking pen/edit/md hides the div and shows raw md in the textarea
    curabox.find('a.nc-curation-toolbox-md').click(function() {       
        var thiscurabox = $(this).parent().parent();        
        var annoareah = 6+ parseInt(thiscurabox.find('div.nc-curation-content')
            .css("height").replace("px",""));                
        thiscurabox.find('div.nc-curation-content').hide();        
        thiscurabox.find('textarea').css("height", annoareah).show();                                
        thiscurabox.find('a.nc-submit').show();
        thiscurabox.find('a.nc-curation-toolbox-preview').show();
        thiscurabox.find('a.nc-curation-toolbox-md').hide();
    });    
    // clicking preview converts textarea md to html, updates the md object in the background
    curabox.find('a.nc-curation-toolbox-preview').click(function() {  
        var thiscurabox = $(this).parent().parent();
        var annoareah = 6+ parseInt(thiscurabox.find('textarea').css("height").replace("px",""));                
        var annomd = thiscurabox.find('textarea').hide().val();   
        // convert from md to html
        var annohtml = nc.utils.md2html(annomd);        
        thiscurabox.find('div.nc-curation-content').css("min-height", annoareah)        
        .html(annohtml).show();        
        thiscurabox.find('a.nc-curation-toolbox-preview,a.nc-curation-toolbox-md').toggle();
    });    
    // clicking save sends the md to the server    
    curabox.find('a.nc-submit').click(function() {  
        var thiscurabox = $(this).parent();
        var annomd = thiscurabox.find('textarea').val(); 
        var annoid = thiscurabox.parent().attr("val");
        nc.updateAnnotationText(annoid, annomd);
        thiscurabox.find('a.nc-curation-toolbox-close').click();
    });    
    // clicking close triggers preview and makes the toolbox disappear
    curabox.find('a.nc-curation-toolbox-close').click(function() { 
        var thiscurabox = $(this).parent().parent();
        thiscurabox.find('a.nc-submit').hide();        
        thiscurabox.find('div.nc-curation-toolbox').hide();
        thiscurabox.find('textarea').css("height","");
        thiscurabox.find('a.nc-curation-toolbox-preview').click();        
    });        
    curabox.find('.nc-curation-content').on("click" , function() {              
        var thiscurabox = $(this).parent(); 
        // check if user is allowed to edit this box           
        if (!nc.curator && thiscurabox.parent().attr("owner")!=nc.userid) {            
            return;
        }
        if (thiscurabox.parent().hasClass('nc-editable-text-visible') && 
            !thiscurabox.find('.nc-curation-toolbox').is(":visible")) {            
            thiscurabox.find('a.nc-curation-toolbox-md').click();            
            thiscurabox.find('.nc-curation-toolbox').show();                      
        }            
    });
   
    return curabox;
}



/* ====================================================================================
 * Comments
 * ==================================================================================== */

/**
 * Creates a box to display a comment or type in a comment
 */
nc.ui.CommentBox = function(uid, rootid, parentid, annoid, annomd) {
    
    // determine if this is a primary comment or a response to a previous comment
    var commentclass = "nc-comment-response";
    if (rootid==parentid) {
        commentclass = "nc-comment-primary";
    } 
    
    var html = '<div class="media" val="'+annoid+'">';    
    html += '<a class="media-left">';
    html += '<img class="media-object '+commentclass+'" src="nc-data/users/'+uid+'.png"></a>';  
    html += '<div class="media-body" val="'+annoid+'"></div></div>';
    
    var commentbox = $(html);
    var commentbody = commentbox.find('.media-body');
    if (annomd=='') {
        commentbody.append('<div class="nc-mb-10"><span class="nc-log-entry-user">Write a new comment</span></div>');
    }
    commentbody.append(nc.ui.AnnoEditBox());
    if (annomd=='') {        
        // this is a blank comment, i.e. an invitation to create a new comment
        commentbody.find('.nc-curation-toolbox,textarea').show();        
        commentbody.find('.nc-curation-toolbox-close').hide();                
        commentbody.find('a.nc-submit').off("click").show()
        .click(function() {        
            var annotext = $(this).parent().find("textarea").val();
            nc.createComment(annotext, rootid, parentid);
        });
    }
    
    // if this is a main comment, add a link to reply to the comment
    // if this is a subcomment, skip this step
    if (rootid==parentid && annomd!='') {
        var rhtml = '<a val="'+annoid+'" class="nc-comment-response">Respond to the comment</a>';
        rhtml = $(rhtml);
        rhtml.click(function() {            
            var responsebox = nc.ui.CommentBox(nc.userid, rootid, annoid, '', '');            
            $(this).hide().parent().append(responsebox);
            responsebox.find('a.nc-save').off("click").show()
            .click(function() {                
                var annotext = $(this).parent().find("textarea").val();
                nc.createComment(annotext, rootid, annoid);
            })
        })
        commentbody.append(rhtml);
    }
    
    // if this is a response to a comment box that's empty, show a close button
    if (rootid!=parentid && annomd=='') {        
        commentbody.find('.nc-curation-toolbox-close').show()
        .off("click").on("click", function() {                  
            commentbody.parent().parent().find('a.nc-comment-response').show();
            commentbody.parent().remove();                                    
        });        
    }
        
    return commentbox;    
}



/**
 * Create one comment box and add it to the nc-comments div
 * @param comdata array with elements datetime, modified, owner_id, root_id, parent_id,
 * anno_id, anno_text
 */
nc.ui.addCommentBox = function(comdata) { //datetime, ownerid, rootid, parentid, annoid, annotext) {
    
    var cbox = $('#nc-comments');       
    var html = '<div class="nc-mb-5 nc-comment-head"><span class="nc-comment-date">'+comdata.datetime+'</span>';    
    html += '<span class="nc-comment-user">'+comdata.owner_id+'</span>';     
    if (comdata.modified !=null) {
        html += '<span class="nc-comment-date"> (edited '+comdata.modified+' by <span class="nc-comment-user">'+comdata.user_id+'</span>)</span>';        
    }
    html+='</div>';        
    var commentbox = nc.ui.CommentBox(comdata.owner_id, comdata.root_id, 
        comdata.parent_id, comdata.anno_id, comdata.anno_text);    
    commentbox.find('textarea').text(comdata.anno_text);
    commentbox.find('.media-body a.nc-curation-toolbox-preview').click();  
    commentbox.find('div.nc-curation-content').css("min-height", 0);
    commentbox.find('.media-body').prepend(html).addClass("nc-editable-text")
    .attr("val", comdata.anno_id).attr("owner", comdata.owner_id);      
    if (comdata.root_id==comdata.parent_id) {        
        cbox.append(commentbox);    
    } else {        
        commentbox.insertBefore ($('.media-body a[val="'+comdata.parent_id+'"]'));
    }        
    // when a new comment is added live, the date is null, make animation
    if (comdata.datetime=='just now') {
        commentbox.hide().show(nc.ui.speed);            
    }    
}

/**
 * 
 */
nc.ui.populateCommentsBox = function(commentarray) {        
    var rootid = $('#nc-comments').attr("val");        
    $.each(commentarray, function(key, val){     
        var obj = val;
        obj.root_id = rootid;                
        nc.ui.addCommentBox(val); 
    })
}
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
            
    // find the value associated with the selected permission level
    var nowform = $('form.nc-form-permissions[val="'+targetid+'"]');
    var nowval = nowform.find("label.active").find("input:radio").val();    
    
    // find the update button for this user
    var btn = nowform.find('button')    
    btn.addClass('btn-warning disabled').html('Updating');    

    // call the update permissions api
    nc.users.updatePermissionsGeneric(targetid, nowval, 
        function (data) {
            nc.utils.alert(data);        
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
