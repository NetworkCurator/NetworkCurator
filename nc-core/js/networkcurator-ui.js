/* 
 * networkcurator-ui.js
 * 
 * Javascript functions for NetworkCurator that generate ui elements,
 * for example widgets for user permissions
 * 
 * 
 */


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
      

/**
 * create a bit of html with a form updating user permissions
 * 
 * netname - string with network name
 * classdata - json encoded array
 * readonly - boolean, true to simplify the tree and avoid editing buttons 
 * 
 */
function ncuiClassTreeWidget(netname, classdata, readonly) {
    
    
}


/**
 * Creates one row in a class tree
 * Row consists of a div with a label and a div below that will hold children
 */
function ncuiClassWidget(netname, classname, parentclass, readonly) {

    var ans1 = '<div class="">classname</div>';
    
    var ans2 = '<div class="nc-classtree-children" id="nc-tree-childrenof-"'+classname+'"></div>';
    
    html += ans1+ans2;

//<div class="nc-tree-node">
    
//</div>
    
}
