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
        var sandmd = $('#nc-sandbox-md');       
        $("#nc-sandbox-md").on('change keyup paste input', function(){
            var textmd = sandmd.val();
            sandout.html(nc.md2html(textmd));      
            });        
    });   
   
