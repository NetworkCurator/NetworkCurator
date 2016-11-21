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
    nc.init.initCuration();    
    nc.init.initMarkdown();    
    nc.init.initComments();        
    nc.init.initOntology();    
    nc.init.initGraph();       
    nc.init.initHistory();
    
    nc.ui.speed = speed;
}

/**
 * Invoked at page startup - builds widgets for managing guest and user permissions 
 */
nc.init.initPermissions = function() {        
    // check if this is the permissions page
    var guestperms = $('#nc-permissions-guest');
    var usersperms = $('#nc-permissions-users');
    var adminnetwork = $('#nc-administration');
    if (adminnetwork.length>0) {
        adminnetwork.hide();
    }
    if (guestperms.length==0 || usersperms.length==0) return;
        
    // creat widgets
    guestperms.append(nc.ui.PermissionsWidget(nc.permissions.guest));                
    usersperms.append(nc.ui.PermissionsWidget(nc.permissions.users));                          
    // prevent disabled buttons being clicked
    $('.btn-group .disabled').click(function(event) {
        event.stopPropagation();
    }); 
    
    // for super-user, create administration widget    
    if (adminnetwork.length>0 || nc.curator==1) {
        adminnetwork.append(nc.ui.AdministrationWidget());
        adminnetwork.show();
    }
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
    nc.graph.initGraph();    
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
            if (!nc.commentator) {
                $('.nc-comment-response').hide();
            }
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
            
    // add handler to show history button
    $('.nc-history-toolbox').css("font-size", $('body').css("font-size"));   
    var historybtn = $('#nc-history');    
    var lockbtn = $('#nc-curation-lock');
    historybtn.on("click",
        function() {         
            // while history editing, disable editing
            lockbtn.toggle();
            // make the history buttons visible
            $('.nc-editable-text').toggleClass('nc-editable-text-history');
            $('.nc-history-toolbox').toggle();            
        });
    
    // for guests who only look, no need for any curation ui widgets or handlers
    if (!nc.commentator) return;
    
    // show curation level ui graphics, add an event to toggle curation on/off
    // this allows owners of comments to edit their own stuff
    $('.nc-curator').show();        
    lockbtn.on("click",
        function() {
            // while editing, disable history previews
            historybtn.toggle();
            // adjust the edit/view buttons
            lockbtn.find('span.glyphicon-pencil, span.glyphicon-lock').toggleClass('glyphicon-pencil glyphicon-lock');
            lockbtn.toggleClass("nc-editing nc-looking"); 
            // for curators, all editable components become active,
            // for non-curators, only those components that are owned by them
            var targets = (nc.curator ? $('.nc-editable-text') : $('.nc-editable-text[owner="'+nc.userid+'"]'));            
            targets.toggleClass('nc-editable-text-visible');
        });    
    
    $('.nc-curation-toolbox').css("font-size", $('body').css("font-size"));   
    
    // certain curator other functions are for curators only    
    if (!nc.curator) {
        $('#nc-permissions-tab').hide(); 
    }
    
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


/**
 * Initialize a history page
 */
nc.init.initHistory = function() {
    if ($('#nc-history-timeline').length==0) return; 
    nc.history.initHistory();    
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
   