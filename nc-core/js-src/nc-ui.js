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
 * common html strings
 * ==================================================================================== */

// a caret span (for dropdowns)
nc.ui.caret = '<span class="pull-right caret nc-dropdown-caret"></span>';


/* ====================================================================================
 * Administration
 * ==================================================================================== */

/**
 * create html with buttons for network administration
 * e.g. purge a network
 */
nc.ui.AdministrationWidget = function() {    
    var ans = $('<div></div>');
    ans.append('<a class="btn btn-danger" role="button">Purge network</a>');    
    ans.find('a').on('click', function() {
        nc.admin.purgeNetwork();
    });
    return ans;
}


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
    if (nc.curator) {
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
    }
     
    // populate the ontology tree
    // uses multiple passes to make all classes display regardless of the order
    // in which they appear in the array    
    do {
        var addagain = 0;
        $.each(classdata, function(key, val){                                        
            addagain += nc.ui.addClassTreeRow(val);          
        });        
    } while (addagain<Object.keys(classdata).length);
    //alert("aab 1");
    //root.DOMRefresh();
    //alert("aab 2");

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
    root.find("div.nc-classdisplay textarea").change();    
    root.find("div.nc-classdisplay").show();        
    root.find("form.nc-classcreate[val='']").show();    
}



/**
 * Creates one row in a class tree
 * Row consists of a div with a label and a div below that will hold children
 * 
 * @param classrow - object with components class_id, etc. 
 */
nc.ui.ClassTreeRowWidget = function(classrow) {
    
    // create objects for displaying, editing, and adding subclasses
    var adisplay = nc.ui.ClassDisplay(classrow);    
    var aform = nc.ui.ClassForm(classrow);
    var cid = classrow['class_id'];    
    var cname = classrow['name'];
    // get the svg defs from nc.ontology.nodes        
    var cdefs = nc.utils.sanitize(classrow.defs, true);        
    var achildren = '<ol class="nc-classtree-children" val="'+classrow['class_id']+'"></ol>';       
    var classvg = '<svg class="nc-symbol" val="'+cid+'"><defs>'+cdefs+'</defs>';
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
        var temp = '<button class="pull-right btn btn-primary btn-sm nc-mw-sm nc-mh-3" ';
        fg += temp +' val="remove">Remove</button>';
        fg += temp +' val="activate" style="display: none">Activate</button>';                       
        fg += temp + ' val="edit">Edit</button>';       
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
 * @param classrow object
 * 
 * should hold attributes class_id, parent_id, connector, directional, name, defs, status 
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
 * Creates an object that controls a dropdown field
 * 
 * @param textlab string that appears in the dropdown button
 * @param btnval string that goes into the button val atrtibute
 */
nc.ui.DropdownGeneric = function(textlab, btnval) {
    var btnhtml = '<div class="btn-group nc-toolbar-group nc-toolbar-group-new" role="group">';    
    btnhtml += '<div class="btn-group nc-dropdown-content" role="group">';  
    btnhtml += '<button class="btn btn-primary dropdown-toggle" val="'+btnval+'" data-toggle="dropdown"><span class="pull-left nc-classname-span">'+textlab+'</span> '+nc.ui.caret+'</button>';
    //btnhtml += '<div class="dropdown-menu"></div>';
    btnhtml += '</div></div>';
    return $(btnhtml);    
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
nc.ui.DropdownObjectList = function(atype, aa, aval, withdeprecated) {

    // create generic dropdown button structure
    var dropb = nc.ui.DropdownGeneric(atype, aval);    
        
    // create list with objects types
    var html = '<ul class="dropdown-menu">';    
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
        
    dropb.find('div.nc-dropdown-content').append(html);
        
    // attach handlers for the dropdown links
    dropb.find("a").click(function() {
        // find the text and add it        
        var nowid = $(this).attr("class_id");
        var nowname = $(this).attr("class_name");        
        var p4 = $(this).parent().parent().parent().parent().find('button.dropdown-toggle');        
        p4.addClass('active').attr("class_name", nowname).attr("selection", nowname).attr("class_id", nowid);         
            p4.find('span.nc-classname-span').html(atype+" "+nowname);        
        $(this).dropdown("toggle");        
        return false;
    });       
            
    return dropb;
}


/* ====================================================================================
 * Widget for toggling display of nodes/links on graph page
 * ==================================================================================== */

/**
 *
 * nodetypes, linktypes - arrays with object, each object holding id, label, val
 * 
 * id - e.g. Cxxxxxx
 * label - e.g. CLASS:SUBCLASS
 * val - SUBCLASS (official class name)
 * show - 0/1 determines whether a node is visible
 * showlabel - 0/1 determines if a text label is visible
 * 
 */
nc.ui.DropdownOntoView = function(nodetypes, linktypes) {
    
    var viewbtn = nc.ui.DropdownGeneric('View', 'view');
                    
    // helper function displays a table row with class name and two checkboxes
    // z - object with class data
    // withlabe - boolean (links don't get showlabel)'
    var makeOneClassRow = function(z, withlab) {        
        var zlabs = -1+z.label.split(":").length;        
        var result = '<tr val="'+z.id+'"><td><span style="padding-left: '+(zlabs*14)+'px">'+z.val+'</span></td>';        
        var c1 = (z.show==1 ? "checked" : "");
        result += '<td class="nc-center"><input type="checkbox"'+c1+' val="show"></input></td>';
        if (withlab) {
            var c2 = (z.showlabel==1 ? "checked" : "");
            result+='<td class="nc-center"><input type="checkbox"'+c2+' val="showlabel"></input></td>';
        } else {
            result+='<td class="nc-center"></td>';
        }
        return result + '</tr>';
    }
    
    // get the dropdown box
    var viewform = viewbtn.find('div.nc-dropdown-content');        
    
    // create rows in a table for node classes
    var f1 = '<tr><th>Nodes</th><th>Visible</th><th>Names</th></tr>';    
    for (var i=0; i<nodetypes.length; i++) {
        f1 += makeOneClassRow(nodetypes[i], true);
    }        
    // for link classes
    var f2 = '<tr><th>Links</th><th>Visible</th><th>Names</th></tr>';    
    for (var i=0; i<linktypes.length; i++) {
        f2 += makeOneClassRow(linktypes[i], false);
    }              
    viewform.append('<div class="dropdown-menu nc-dropdown-form"><table>'+f1+f2+'</table><br/></div>');
            
    return viewbtn;
}


/* ====================================================================================
 * Widget for graph display settings
 * ==================================================================================== */

/**
 * Creates a widget with a form holding some graph display settings
 * 
 */
nc.ui.DropdownGraphSettings = function() {
    var settingsbtn = nc.ui.DropdownGeneric('Settings', 'settings');
    var settingsform = settingsbtn.find('div.nc-dropdown-content')
    
    // contents of the widget form
    var html = '<table>';
    html += '<tr><th colspan="3">Display</th></tr>';
    html += '<tr><td><span>Wide view</span></td><td><input val="wideview" type="checkbox"></td><td></td></tr>';    
    html += '<tr><td><span>Hover tooltip</span></td><td><input val="tooltip" type="checkbox"></td><td></td></tr>';    
    html += '<tr><td><span>Inactive objects</span></td><td><input val="inactive" type="checkbox"></td><td></td></tr>';    
    html += '<tr><td><span>Label size</span></td><td><input val="namesize" type="text"></td><td><span class="info">(pt)</span></td></tr>';    

    html += '<tr><th colspan="3">Layout</th></tr>';
    html += '<tr><td><span>Force layout</span></td><td><input val="forcesim" type="checkbox"></td><td></td></tr>';
    html += '<tr><td><span>Link length</span></td><td><input type="text" val="linklength"></td><td><span class="info">(au, e.g. [20,100])</span></td></tr>';
    html += '<tr><td><span>Node strength</span></td><td><input type="text" val="strength"></td><td><span class="info">(au, e.g [-100, -20]</span></td></tr>';
    html += '<tr><td><span>Velocity decay</span></td><td><input type="text" val="vdecay"></td><td><span class="info">(au, [0,1])</span></td></tr>';
    
    html += '<tr><th colspan="3">Traversal</th></tr>';
    html += '<tr><td><span>Local neighborhood</span></td><td><input val="local" type="checkbox" checked></td><td></td></tr>';
    html += '<tr><td><span>Neighborhood distance</span></td><td><input type="text" val="neighborhood"></td><td><span class="info">(# steps)</span></td></tr>';
    
    html += '</table><br/>';
    
    settingsform.append('<div class="dropdown-menu nc-dropdown-form">'+html+'</div>')
    return settingsbtn;
}


/* ====================================================================================
 * Widget for graph save options
 * ==================================================================================== */

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
nc.ui.DropdownGraphSave = function() {

    // create generic dropdown button structure
    var dropb = nc.ui.DropdownGeneric('Save', 'save');    
        
    // create list with objects types
    var html = '<ul class="dropdown-menu">';    
    html += '<li><a val="diagram" href="#">Network diagram (svg)</a></li>';
    //html += '<li><a val="definition" href="#">Network definition (json)</a></li>';
    html += '<li><a val="nodes" href="#">Node list (txt)</a></li>';
    html += '</ul>';
        
    dropb.find('div.nc-dropdown-content').append(html);
                    
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
        var annomd = thiscurabox.find('textarea.nc-curation-content').hide().val();   
        // convert from md to html
        var annohtml = nc.utils.md2html(annomd);        
        thiscurabox.find('div.nc-curation-content').css("min-height", annoareah)        
        .html(annohtml).show();        
        thiscurabox.find('a.nc-curation-toolbox-preview,a.nc-curation-toolbox-md').toggle();
    });    
    // clicking save sends the md to the server    
    curabox.find('a.nc-submit').click(function() {  
        var thiscurabox = $(this).parent();        
        var annotext = thiscurabox.find('textarea');        
        var annomd = thiscurabox.find('textarea.nc-curation-content').val(); 
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


/* ====================================================================================
* Users table
* ==================================================================================== */

/**
 * 
 * @param ulist object with a set of user data
 * @param objname target div to hold the users table
 */
nc.ui.createUsersTable = function(ulist, objname) {
    
    var objdiv = $('#'+objname);
    
    // create a table object
    var html = '<table class="table"><tr><th>User id</th><th>Name</th><th>Status</th></tr>';
    var tdco = '</td><td>';
    for (var i=0; i<ulist.length; i++) {
        var temp = '<tr><td>'+ulist[i]['id']+tdco+
            ulist[i]['firstname']+' '+ulist[i]['middlename']+' '+ulist[i]['lastname'];
        temp += tdco + ulist[i].status+'</td></tr>';
        html += temp;        
    }        
    html += '</table>';
    
    objdiv.html(html);
    
}
