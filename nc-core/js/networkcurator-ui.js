/* 
 * networkcurator-ui.js
 * 
 * Javascript functions for NetworkCurator that generate ui elements,
 * for example widgets for user permissions
 * 
 * 
 */


/* ==========================================================================
 * Permissions
 * ========================================================================== */

/**
 * create html with a form for updating user permissions
 * 
 * netname - string with network name
 * udata - json encoded array, each element should be an object describing 
 * one user
 */
function ncuiPermissionsWidget(netname, udata) {
        
    // internal function for making a button
    // val - 0-3 determines label on the button
    // uid - the user id (used to disactivate some buttons for the guest user)
    // perm - 0-3 permission level for this user
    // returns an <label></label> object
    function ncuiPB(val, uid, perm) {
        // convert a numeric value into a label        
        var vl = ["None", "View", "Comment", "Edit", "Curate"];
        var lab = vl[val];       
        if (perm==val) {
            perm = " active";
        } else {
            perm = "";
        }
        if (uid=="guest" && val>1) perm = " disabled";            
                
        // create a <label> html 
        var html = '<label class="btn btn-default nc-btn-permissions'+perm+'">';
        html += '<input type="radio" autocomplete="off" value="'+val+'" '+perm+'>';
        html += lab+'</label>';        
        return html;
    }
         
    var ans = '';
    $.each(udata, function(key, val){        
        var uid = val['user_id'];        
        var up = val['permissions'];        
        var nowlab = uid;        
        if (uid != "guest") {            
            nowlab += " ("+val['user_firstname']+" "+val['user_middlename']+" "+val['user_lastname']+")";
        }
        
        // structure will be form > form-group with (label, btn-group, btn)
        var html = '<form id="ncf-permissions-'+ uid+'" class="form-inline nc-form-permissions" onsubmit="return false;">';        
        html += '<div class="form-group" id="ncfg-permissions-'+uid+'" style="width:100%">';
        html += '<label class="col-form-label nc-fg-name">'+nowlab+'</label>';
        html += '<div class="btn-group" data-toggle="buttons">';                
        for (var pp=0; pp<5; pp++) 
            html += ncuiPB(pp, uid, up);   
        html += '</div>'; // closes the btn-group
        html += '<button class="btn btn-success" ';
        html += 'onclick="javascript:ncUpdatePermissions(\''+netname+ '\' , \''+uid+'\'); return false;">';
        html += 'Update</button></div></form>';                
        
        // append to the main answer
        ans += html;
    });        
        
    return ans;                            
}
      

/* ==========================================================================
 * Ontology
 * ========================================================================== */

/**
 * create a bit of html with a form updating user permissions
 * 
 * netname - string with network name, assumed global variable
 * classdata - array with existing class structure
 * isnodes - boolean (true to populate node class tree, false to populate link tree)
 * readonly - boolean, true to simplify the tree and avoid editing buttons 
 * 
 */
function ncuiClassTreeWidget(netname, classdata, islink, withbuttons) {
        
    // get the root div for the treee
    var root = $('#nc-ontology-nodes');    
    if (islink) {
        root = $('#nc-ontology-links');        
    }                 
          
    // create a div for children and a new form
    var rootrow = {
        parent_id:'',
        class_id:'',
        class_name:'', 
        connector:+islink, 
        directional:0
    };
    //var parentsofroot = ncuiClassDisplay(rootrow, 0);
    var parentsofroot = '<ol class="nc-classtree-children" val="">'; 
    //rootrow['class_name']='';    
    parentsofroot += '</ol>';        
    parentsofroot += ncuiClassForm(rootrow, withbuttons);    
    root.append(parentsofroot);
             
    // set up drag-drop of classes     
    var oldContainer;
    root.find(".nc-classtree-children").sortable({
        handle: 'button.nc-btn-move',
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
            }, 1500);
        }
    });
     
    // populate the ontology tree
    $.each(classdata, function(key, val){        
        // here val holds a record for a class, create a class entry            
        ncAddClassTreeChild(val, withbuttons);          
    });
        
    // create functions that respond to events on the tree
    // submitting a new class
    root.delegate("form.nc-classcreate", "submit", function() {
        var parentid=$(this).attr('val');        
        var newclassname = $(this).find("input").val();            
        var isdirectional = +$(this).find("input.form-check-input").is(":checked");        
        ncCreateNewClass(netname, parentid, newclassname, islink, isdirectional); 
    });  
    // clicking to edit an existing class
    root.delegate("div.nc-classdisplay button[val='edit']", "click", function() {                        
        var classid = $(this).parent().attr('val');
        // make sure input box shows current classname
        var classname = $(this).parent().find('.nc-classdisplay-span').html();        
        var thisform = root.find("form.nc-classupdate[val='"+classid+"']");
        thisform.find('input').val(classname);
        // toggle visibility
        thisform.toggle();        
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();        
    });    
    // clicking to remove a class
    root.delegate("div.nc-classdisplay button[val='remove']", "click", function() {                                
        var classid = $(this).parent().attr('val');                      
        ncRemoveClass(netname, classid)
    });
    // clicking to cancel updating an existing class
    root.delegate("form.nc-classupdate .nc-btn-class-cancel", "click", function() {                                
        var classid = $(this).attr('val');              
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
        root.find("form.nc-classupdate[val='"+classid+"']").toggle();                
    });
    // clicking to update the value of an existing class
    root.delegate("form.nc-classupdate .nc-btn-class-update", "click", function() {        
        var thisform = $(this).parent().parent();
        var classid=thisform.attr('val');
        var parentid = thisform.parent().parent().attr('val');        
        var newclassname = thisform.find("input").val();            
        var islink = thisform.find("input.form-check-input").length>0;
        var isdirectional = 0+thisform.find("input.form-check-input").is(":checked");        
        ncUpdateClassProperties(netname, classid, newclassname, parentid, islink, isdirectional);        
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
        root.find("form.nc-classupdate[val='"+classid+"']").toggle();                
    });    
    
    // after all the tree is populated, only display a small number of elements    
    //root.find("form.nc-classupdate").hide();    
    root.find("div.nc-classdisplay").show();    
    root.find("form.nc-classcreate[val='']").show();    
}


/**
 * Creates one row in a class tree
 * Row consists of a div with a label and a div below that will hold children
 */
function ncuiClassTreeRowWidget(classrow, withbuttons) {
    
    // create objects for displaying, editing, and adding subclasses
    var adisplay = ncuiClassDisplay(classrow, withbuttons);    
    var aform = ncuiClassForm(classrow, withbuttons);
    var achildren = '<ol class="nc-classtree-children" val="'+classrow['class_id']+'"></ol>';       
    
    // create the widget from the components
    return '<li val="'+classrow['class_id']+'">'+adisplay + aform + achildren+'</li>';                
}


/**
 * Creates html that makes up the one row in the classtree (when viewing only)
 * 
 * classrow - array with details on this class
 * withbuttons - logical, set true to display buttons on the RHS.
 * 
 */
function ncuiClassDisplay(classrow, withbuttons) {
    
    var classid = classrow['class_id'];
    var classname = classrow['class_name'];    
    var directional = +classrow['directional'];
    
    // create a div with one label (possible a directional comment) and one button
    var fg = '<div val="'+classid+'" class="nc-classdisplay"><span class="nc-classdisplay-span">'+classname+'</span>';
    // forms for links include a checkbox for directional links    
    fg += '<span class="nc-classdisplay-span nc-directional">';
    if (directional) {
        fg+= ' (directional)';
    }
    fg+='</span>';     
    if (withbuttons) {
        fg += '<button val="remove" class="pull-right btn btn-primary btn-sm nc-btn-remove">Remove</button>';           
        fg += '<button val="edit" class="pull-right btn btn-primary btn-sm nc-btn-edit">Edit</button>';   
        fg += '<button val="move" class="pull-right btn btn-primary btn-sm nc-btn-move">Move</button>';       
    }
    fg += '</div>'; 
                
    return fg;
}

/**
 * Creates a form that asks the user for a new class name and shows a submit button
 * 
 * parentid - name of parent class (or empty if root)
 * islink, isdirectional - settings for link class configuration
 * classname - name of existing class (or empty if new class)
 */
function ncuiClassForm(classrow, withbuttons) {
    
    if (!withbuttons) {
        return "";
    }
    
    var classid = classrow['class_id'];    
    var classname = classrow['class_name'];
    var islink = +classrow['connector'];
    var directional = +classrow['directional'];
        
    var formclass = 'nc-classupdate';
    if (classname=='') {
        formclass = 'nc-classcreate';
    } 
    
    // create the form
    var ff ='<form val="'+classid+'" style="display: none" class="form-inline nc-class-form '+formclass+'" onsubmit="return false">';
    // create the textbox asking for the classname
    var fg = '<div class="form-group"><input class="form-control input-sm" placeholder="Class name"';
    if (classname!='') {
        fg+= 'value="'+classname+'"';
    }
    fg += '></div>';        
    // forms for links include a checkbox for directional links
    if (islink) {        
        fg += '<div class="form-group"><label class="form-check-inline"><input type="checkbox" class="form-check-input"';
        if (directional) {
            fg+= ' checked';
        }
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
        fg += '</div>';                           
    }
    var ff2 = '</div></form>';
       
    return ff+fg+ff2;    
}


/**
 * Create a row in a class tree and insert it into the page
 */
function ncAddClassTreeChild(classrow, withbuttons) {  
    
    // find the root of the relevant tree    
    var root = $('#nc-ontology-nodes');    
    if (+classrow['connector']) {        
        root = $('#nc-ontology-links');        
    }  
            
    // find the target div where to insert the node
    var parentid = classrow['parent_id'];    
    var targetdiv = root.find('ol.nc-classtree-children[val="'+parentid+'"]');            
    
    // create the widget for this class
    var newobj = $(ncuiClassTreeRowWidget(classrow, withbuttons));
    newobj.hide();
    
    // figure out whether to insert before the form or at the end    
    if (targetdiv.children("form.nc-classcreate").length > 0) {         
        $(newobj).insertBefore(targetdiv.children('form.nc-classcreate'));
    } else {               
        targetdiv.append(newobj);
    }   
    targetdiv.find("li").show('normal');
    targetdiv.find("div.nc-classdisplay").show('normal');    
    
    
}



/* ==========================================================================
 * Log
 * ========================================================================== */

/**
 * Create a toolbar for the activity log
 */
function ncBuildActivityLogToolbar(netname, logsize) {
    //alert("here");
    var html = '<ul class="pagination">';
    var numpages = logsize/50;
    
    for (var i=0; i<numpages; i++) {
        html += '<li value='+i+'><a href="javascript:ncLoadActivityPage(\''+netname+'\', '+i+', '+50+')">'+(i+1)+'</a></li>';
    }
    html += '</ul>';
        
    $('#nc-activity-log-toolbar').append(html);           
    ncLoadActivityPage(netname, 0, 50);
}

/**
 * data - an array of arrays
 * each element in array should hold data on one row in the log table
 */
function ncPopulateActivityArea(data) {
    var ans = '';
    $.each(data, function(key, val){        
        ans += ncFormatOneLogEntry(val);
    });
    $('#nc-activity-log').html(ans);
}

/**
 * Provides html to write out one line in the log table
 */
function ncFormatOneLogEntry(data) {
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




/* ==========================================================================
 * Curation
 * ========================================================================== */

/**
 * Create a div with a button toolbox for curation
 * 
 * mdconvert - a showdown object use to convert between md and html
 * 
 */
function ncuiMakeAnnoEditBox(mdconverter) {
    
    // write static html to define components of the toolbox
    var html = '<div class="nc-curation-box"><div class="nc-curation-toolbox" style="display: none">';
    html += '<a role="button" class="nc-curation-toolbox-md btn btn-sm btn-default" >Edit</a>';
    html += '<a role="button" class="nc-curation-toolbox-preview btn btn-sm btn-default">Preview</a>';    
    html += '<a role="button" class="nc-curation-toolbox-close pull-right">close</a>';
    html += '</div><div class="nc-curation-content"></div>';
    html += '<textarea class="nc-curation-content" style="display: none"></textarea>';
    html += '<a role="button" class="btn btn-sm btn-success nc-save" style="display: none">Save</a></div>';    
    
    // create DOM objects, then add actions to the toolbox buttons
    var toolbox = $(html);
            
    // clicking pen/edit/md hides the div and shows raw md in the textarea
    toolbox.find('a.nc-curation-toolbox-md').click(function() {
        var annodiv = $(this).parent().parent();                             
        annodiv.find('div.nc-curation-content').hide();
        annodiv.find('textarea').html(nc_md[annodiv.attr("val")]).show();                        
        annodiv.find('a.btn-success').show();
    });
    // clicking preview converts textarea md to html, updates the md object in the background
    toolbox.find('a.nc-curation-toolbox-preview').click(function() {        
        var annodiv = $(this).parent().parent().parent();  
        var textdiv = annodiv.find('textarea');
        //var textheight = textdiv.height();
        var annomd = textdiv.hide().val();                      
        nc_md[annodiv.attr("val")] = annomd;        
        annodiv.find('div.nc-curation-content').html(mdconverter.makeHtml(annomd)).show()      
    });
    // clicking save sends the md to the server    
    toolbox.find('a.nc-save').click(function() {                
        $(this).parent().find('a.nc-curation-toolbox-preview').click();
        var annoid = $(this).parent().parent().attr("val");        
        ncUpdateAnnotationText(annoid);
    });
    // clicking close triggers preview and makes the toolbox disappear
    toolbox.find('a.nc-curation-toolbox-close').click(function() {        
        var thisp = $(this).parent();
        thisp.parent().find('a.nc-save').hide("normal");        
        thisp.hide("normal").find('a.nc-curation-toolbox-preview').click();        
    });
        
    //var allcontents = ;    
    toolbox.find('.nc-curation-content').on("click" , function() {       
        var box = $(this).parent().parent();
        if (box.hasClass("nc-editable-text-visible")) {
            box.find('.nc-curation-toolbox').show('normal');
            box.find('div.nc-curation-content').hide();
            box.find('textarea').show();
            box.find('a.nc-save').show();
        }        
    });
        
    return toolbox;
}



/* ==========================================================================
 * Comments
 * ========================================================================== */

/**
 * Creates a box where the user can type in a new comment
 */
function ncuiMakeCommentBox(uid, rootid, parentid, commenttext, mdconverter) {
    
    var html = '<div class="media">';
    if (commenttext=='') {
        html += '<p>Write a new comment</p>';
    }
    html += '<a class="media-left">'
    html += '<img class="media-object" src="nc-data/users/'+uid+'.png"></a>';  
    html += '<div class="media-body"></div></div>'
    
    var commentbox = $(html);
    commentbox.find('.media-body').append(ncuiMakeAnnoEditBox(mdconverter));
    if (commenttext=='') {
        commentbox.find('.nc-curation-toolbox').toggle();
        commentbox.find('.nc-curation-content').toggle();
        commentbox.find('.nc-curation-toolbox-close').hide();
        // redefine the click event on the  submit button
        // here, send the 
        commentbox.find('a.nc-save').off("click").show();
        commentbox.find('a.nc-save').click(function() {        
            var annotext = $(this).parent().find("textarea").val();
            ncCreateNewComment(annotext, rootid, parentid);
        });
    }
    
    return commentbox;    
}



function ncuiAddCommentBox(datetime, ownerid, rootid, parentid, annoid, annotext, mdconverter) {
    var cbox = $('#nc-comments');   
    
    var html = '<div class="nc-mb-5"><span class="nc-log-entry-date">'+datetime+'</span>';
    html += '<span class="nc-log-entry-user">'+ownerid+'</span></div>';    
    var commentbox = ncuiMakeCommentBox(ownerid, rootid, parentid, annotext, mdconverter);
    nc_md[annoid] = annotext;
    commentbox.find('textarea').html(annotext);
    commentbox.find('.media-body a.nc-curation-toolbox-preview').click();                        
    commentbox.find('.media-body').prepend(html)
    .addClass("nc-editable-text").attr("val", annoid);    
    cbox.append(commentbox);    
    
    // when a new comment is added live, the date is null, make animation
    if (datetime=='just now') {
        commentbox.hide();    
        commentbox.show('normal');
    }
}

/**
 * 
 */
function ncuiPopulateCommentsBox(comments, mdconverter) {
    //alert("popcombox");
    var cbox = $('#nc-comments');
    var rootid = cbox.attr("rootid");
    
    //alert("ppp "+rootid);
    $.each(comments, function(key, val){                   
        if (val['parent_id']==rootid) {
            // first-level comment
            ncuiAddCommentBox(val['datetime'], val['owner_id'], rootid, val['parent_id'], 
                val['anno_id'], val['anno_text'], mdconverter);            
        } else {
            // sub-comment
            alert("subcomment");
        }
    })
}