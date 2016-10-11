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

/**
 * Invoked when user changes a form element.
 * 
 * This scans all form elements and generated a json object. Pastes this json object
 * into the markdown box
 */
nc.sandbox.generateMarkdown = function() {    
    var req = $('#nc-sandbox-required');
    var opt = $('#nc-sandbox-optional');
    
    // start building an object
    var result = {};
        
    // conversion function transfers data from a form group to result {}   
    var fg2obj = function() {
        var iname = $(this).attr("val");       
        var iinput = $(this).find("input");   
        var now = "";
        if (iinput.length==1) {           
            now = iinput.val();              
            result[iname] = (isNaN(now) || now=='' ? now : +now);            
        } else if (iinput.length>1) {            
            result[iname] = [];
            iinput.each( function() {
                var now = $(this).val();
                now = (isNaN(now) || now=='' ? now : +now);
                result[iname].push(now);  
            });
        } else {            
            var xx = $(this).find("textarea").val();            
            if (xx!="") {                
                var data = xx.split("\n");                
                var colnames = ($(this).attr("colnames")).split(" ");                                
                result[iname] = [];
                for (var i=0; i<data.length; i++) {
                    if (data[i]=='') {
                        data[i]=' \t';
                    }
                    var dd = {};
                    data[i] = data[i].split("\t");
                    for (var j=0; j<data[i].length; j++) {
                        now = data[i][j];
                        now = (isNaN(now) || now=='' || now==' ' ? now : +now);
                        dd[colnames[j]] = now;
                    }
                    result[iname].push(dd);
                }
            }
        }
    }
        
    req.find('.form-group').each( fg2obj);
    opt.find('.form-group').each( fg2obj);
            
    var mdout = '```mdalive '+req.attr("val")+'\n';
    mdout+=JSON.stringify(result, null, 2)+'\n';
    mdout += '```';
    
    $('#nc-sandbox-md').val(mdout).change();    
}


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
        // code that will generate the preview
        var sandmd = $('#nc-sandbox-md');       
        $("#nc-sandbox-md").on('change keyup paste input', function(){
            var textmd = sandmd.val();
            sandout.html(nc.utils.md2html(textmd));      
        });        
        // gui toggling
        var sandopt = $('#nc-sandbox-optional').hide();
        $('.nc-sandbox-optional').css("cursor", "pointer").click(function() { 
            sandopt.toggle();
            $(this).find('span').toggleClass("dropup");
        });
    
        // using two spaces for tabs in textarea
        $('textarea').on('keyup', function(e) {            
            if (e.keyCode==32) {
                var nowval = $(this).val();
                var nowpos = $(this).prop("selectionStart");                   
                if (nowval.substr(nowpos-2, 2)=='  ') {                    
                    $(this).val(nowval.substr(0, nowpos-2)+"\t"+nowval.substr(nowpos));
                    $(this).prop("selectionStart", nowpos-1).prop("selectionEnd", nowpos-1);
                }                
            }
        });
    
        // generate markdown from the forms
        $('.form-group input, .form-group textarea').on("keyup paste input", nc.sandbox.generateMarkdown);    
            
    });   
   
