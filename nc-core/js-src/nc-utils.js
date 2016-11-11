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
nc.utils.allowedTags = ['h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 
    'p', 'a', 'ul', 'ol',
    'nl', 'li', 'b', 'i', 'strong', 'em', 'strike', 'code', 'hr', 'br', 'div',
    'table', 'thead', 'caption', 'tbody', 'tr', 'th', 'td', 'pre',   
    'g', 'circle', 'rect', 'line', 'path', 'polyline', 'ellipse', 'polygon', 'marker'];
nc.utils.allowedAttributes= {
    code: ['class'],
    style: ['type'],
    g: ['id', 'transform'],
    circle: ['cx', 'cy', 'r', 'id', 'fill', 'strok*'],
    rect: ['x', 'y', 'rx', 'ry', 'width', 'height', 'id', 'fill', 'strok*'],
    marker: ['id', 'viewbox', 'ref*', 'mark*', 'orient'],
    line: ['x1', 'x2', 'y1', 'y2', 'id', 'strok*'],
    path: ['d', 'fill', 'strok*']
}


// conversion from markdown to html (sanitized and alive)
nc.utils.md2html = function(x) {
    var x2 = nc.utils.sanitize(nc.utils.mdconverter.makeHtml(x), false);
    return makealive.convert(x2);   
}


// sanitize a piece of html
// x - a string with text (dirty html)
// allowstyle - logical. Set true to allow the <style> tag.
nc.utils.sanitize =function(x, allowstyle) {    
    var oktags = nc.utils.allowedTags.slice(0);
    if (allowstyle) {
        oktags.push('style');
    }   
    //alert("A: "+JSON.stringify(oktags)+" ++++ "+JSON.stringify(nc.utils.allowedAttributes));
    return sanitizeHtml(x, {
        allowedTags: oktags,
        allowedAttributes: nc.utils.allowedAttributes 
    });    
}


var at1 = '<marker id="asd"></marker>';
var at2 = '<marker id="asd" refX="4"></marker>';

//alert(at1+" "+nc.utils.sanitize(at1));
//alert(at2+" "+nc.utils.sanitize(at2));
//alert(at2+" "+sanitizeHtml(at2, {allowedTags: nc.utils.allowedTags, allowedAttributes: nc.utils.allowedAttributes}));

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



/* ====================================================================================
 * Saving blobs
 * ==================================================================================== */

// function creates a blob file containing text data d
nc.utils.saveToFile = function (d, filename) {

    // create blob with data
    var data = new Blob([d], {
        type: 'text/plain'
    });
    
    // create a handle for the data
    var fileurl = window.URL.createObjectURL(data);
        
    // hack: use an a element to hold a link to the url
    var templink = document.createElement('a');    
    templink.setAttribute('download', filename);
    templink.href = fileurl;    
    document.body.appendChild(templink);

    // activate the link to trigger download, discard temporary link
    window.requestAnimationFrame(function () {                        
        templink.dispatchEvent(new MouseEvent('click'));        
        document.body.removeChild(templink);        
    });
        
};
