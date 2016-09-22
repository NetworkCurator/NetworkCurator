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


/* ==========================================================================
 * Building/Managing ontology structures
 * ========================================================================== */

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
            var newrow = {
                class_id:data['data'], 
                parent_id:'', 
                connector:+islink,
                directional:+isdirectional, 
                name:classname,
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
        exit();
    }
                
    // must translate between classid and targetname 
    // also between parentid and parentname
    var targetname = $('div.nc-classdisplay[val="'+classid+'"] span[val="nc-classname"]').html();
    alert("targetname "+targetname);
    var parentname = parentid;
    if (parentid!='') {
        parentname = $('div.nc-classdisplay[val="'+parentid+'"] span[val="nc-classname"]').html();
    }
        
        alert("got "+targetname+" "+parentname+" "+parentid);
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "updateClass", 
        network: nc.network,
        target: targetname,
        name: classname,
        title: classname,
        'abstract': '',
        content: '',
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
            targetdisplay.find('span.nc-comment[val="nc-classname"]').html(classname);
            var dirtext = '';
            if (isdirectional) {
                dirtext = ' (directional)';
            } 
            targetdisplay.find('span.nc-comment[val="nc-directional"]').html(dirtext);
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
    if (action=="deprecate") {
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