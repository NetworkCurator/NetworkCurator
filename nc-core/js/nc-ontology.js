/* 
 * nc-ontology.js
 * 
 * 
 */


if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.ontology = {};


/* ==========================================================================
 * Building/Managing ontology structures
 * ========================================================================== */

/**
 * Invoked when user wants to create a new class for a node or link
 */
nc.ontology.createClass = function(parentid, classname, islink, isdirectional) {
    
    // check the class name string     
    if (nc.utils.checkString(classname, 1)<1) {
        nc.msg("Hey!", "Invalid class name");
        return;
    }
    
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "createNewClass", 
        network_name: nc.network,
        class_name: classname,
        parent_id: parentid,  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {
        nc.utils.alert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {                
            // insert was successful, so append the tree            
            var newrow = {
                class_id:data['data'], 
                parent_id:parentid, 
                connector:+islink,
                directional:+isdirectional, 
                class_name:classname
            };
            nc.ui.addClassTreeRow(newrow, 1);                
        }
    });  
}

/**
 * Send a request to update a class name or parent structure
 */
nc.ontology.updateClassProperties = function(classid, classname, parentid, 
islink, isdirectional) {
        
    if (nc.utils.checkString(classname, 1)<1) {
        nc.msg("Hey!", "Invalid class name");
        exit();
    }
    
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "updateClass", 
        network_name: nc.network,
        class_id: classid,
        class_name: classname,
        class_status: 1,
        parent_id: parentid,  
        connector: +islink,
        directional: +isdirectional        
    }, function(data) {
        nc.utils.alert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
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

/**
 * Sends a request to remove/disactivate a class 
 */ 
nc.ontology.removeClass = function(classid) {
           
    $.post(nc.api, {
        controller: "NCOntology", 
        action: "removeClass", 
        network_name: nc.network,
        class_id: classid        
    }, function(data) {
        nc.utils.alert(data);      
        data = $.parseJSON(data);
        if (data['success']==false) {              
            nc.msg('Error', data['errormsg']);                
        } else {   
            if (data['success']==true) {
                // the class has been truly removed
                $('li[val="'+classid+'"]').fadeOut('normal', function() {
                    $(this).remove()
                } ); 
            } else {
                // the class has been deprecated
                
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
            thisname = val['class_name'],
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
                    ontolist[thisid].class_longname = longname;
                    counter++;
                } 
            }
        });       
    }
    
    return ontolist;            
}