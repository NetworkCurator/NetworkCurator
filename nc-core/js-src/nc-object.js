/* 
 * nc-object.js
 * 
 * (Code that handles editing of annotations for graph objects)
 * 
 */

/* global nc */


if (typeof nc === "undefined") {
    throw new Error("nc is undefined");
}
nc.object = {};


/* ====================================================================================
 * Setup at the beginning
 * ==================================================================================== */

/**
 * invoked from nc-core. Creates a toolbar that allows users to change
 * object name, class, and owner for graph objects (nodes, links)
 * 
 */
nc.object.initToolbar = function() {
    
    var otbr = $('#nc-object-toolbar');    
    if (otbr.length===0) {
        return;
    }       
    var oid = otbr.attr("objectid");
    var oname = otbr.attr("objectname");
    var oclass = otbr.attr("objectclass");
    var oowner = otbr.attr("objectowner");
    var annoid = otbr.attr("objectannoid");
    
    otbr.append(nc.ui.NameDropdown(oname, annoid, oid));
    otbr.append(nc.ui.ClassDropdown(oclass, oid));
    otbr.append(nc.ui.OwnerDropdown(oowner, oid));        
    otbr.find('span.nc-dropdown-caret').hide();
    otbr.find('button').addClass("disabled");
};
    

/**
 * function called from a confirmatory modal 
 * sends a request to server to update the classname associated with an object id
 * @param objid
 * @param newclassname
 */
nc.object.confirmUpdateClass = function(objid, newclassname) {    
    
    // api call to update an object class. Displays errors or updates UI toolbar
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "updateClass", 
        network: nc.network,
        target_id: objid,
        "class": newclassname
    }, function(data) {           
        nc.utils.alert(data);        
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            if (!data['success']) {  
                nc.msg('Error', data['errormsg']);                
            } else {
                // change the label on the ui button                
                var btng = $('#nc-object-toolbar .btn-group[val="class"]');                
                btng.find('span.nc-classname-span').html("Ontology class: "+newclassname);
            }
        }      
    });
};


/**
 * function called from a confirmatory modal
 * sends request to server to update the name annotation associated with an object id 
 * 
 * @param annoid - string, the target annotation id, e.g. Txxxxx0
 * @param newname - string, the new object name
 */
nc.object.confirmUpdateName = function(annoid, newname) {    
    
    // api call to update annotation. This is similar to nc.updateAnnotationText
    // but here the end-handler also updates the object GUI component
     $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "updateAnnotationText", 
        network: nc.network,
        anno_id: annoid,
        anno_text: newname
    }, function(data) {           
        nc.utils.alert(data);  
        data = JSON.parse(data);
         if (nc.utils.checkAPIresult(data)) {            
            if (!data['success']) {
                nc.msg('Error', data['errormsg']);                
            } else {
                // change the label on the ui button                
                var btng = $('#nc-object-toolbar .btn-group[val="name"]');                
                btng.find('span.nc-classname-span').html("Object name: "+newname);
            }
        }                  
    });
};


/**
 * function called from a confirmatory modal
 * sends request to server to update the owner of an object
 * @param objid
 * @param newowner
 */
nc.object.confirmUpdateOwner = function(objid, newowner) {
    
    // api call to update an object owner. Displays errors or updates UI toolbar
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "updateOwner", 
        network: nc.network,
        target_id: objid,
        "owner": newowner
    }, function(data) {           
        nc.utils.alert(data);        
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            if (!data['success']) {              
                nc.msg('Error', data['errormsg']);                
            } else {
                // change the label on the ui button                
                var btng = $('#nc-object-toolbar .btn-group[val="owner"]');                
                btng.find('span.nc-classname-span').html("Managed by: "+newowner);
            }
        }      
    });
};

