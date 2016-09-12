/* 
 * nc-utils.js
 * 
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.utils = {};


/* ==========================================================================
 * Debugging (during development)
 * ========================================================================== */

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



/* ==========================================================================
 * Some general purpose functions (e.g. string and form checking)
 * ========================================================================== */


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
        nc.utils.alert("Something wrong in API result: "+ x.toString());        
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
        var x = a[key]; var y = b[key];
        if (x<y) {
            return -1;
        } else if (x>y) {
            return 1;
        } else {
            return 0;
        }        
    });
}