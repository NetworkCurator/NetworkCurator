/* 
 * nc-sandbox.js
 * 
 * Interactive md and makealive sandboxes
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

    // helper function converts long text into an array with objcets
    var string2matrix = function(sdata, colnames) {
        var data = sdata.split("\n");  
        var result = [];              
        
        // for no-columns, convert into a simple array
        if (colnames.length==1 && colnames[0]=='') {
            for (var i=0; i<data.length; i++) {
                if (data[i]!='') {                                                                
                    result.push(data[i].split("\t")[0]);
                }                
            }
            return result;
        }
        
        // if reached here, convert into an array of objects
        for (var i=0; i<data.length; i++) {
            if (data[i]!='') {                        
                var dd = {};
                data[i] = data[i].split("\t");
                for (var j=0; j<data[i].length; j++) {
                    var now = data[i][j];
                    now = (isNaN(now) || now=='' || now==' ' ? now : +now);
                    dd[colnames[j]] = now;
                }
                result.push(dd);
            }                
        }
        return result;
    }

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
                var colnames = ($(this).attr("colnames")).split(" ");                                 
                result[iname] = string2matrix(xx, colnames);                
            }
        }
    }
        
    req.find('.form-group').each( fg2obj);
    opt.find('.form-group').each( fg2obj);
            
    var mdout = '```makealive '+req.attr("val")+'\n';
    mdout+=JSON.stringify(result, null, 2)+'\n';
    mdout += '```';
    
    $('#nc-sandbox-md').val(mdout).change();    
}


/* ====================================================================================
 * A converter function to generate sandboxes on-the-fly
 * ==================================================================================== */

/**
 * this is a conversion function that generates html for a sandbox
 *
 * @param x object holding an attribute "sandbox"
 *
 * @return
 * 
 * The function calls the sandbox function (e.g. barplot01) with null object to receive
 * a definition of all the expected and optional arguments. 
 * 
 * Then generates html for all the attributes
 *
 */
makealive.lib.sandbox = function(obj, x) {
    
    // fetch the help output from the sandbox function
    var funname = sanitizeHtml(x.sandbox);
    try {
        var funargs = makealive.lib[funname](null, "");    
    } catch(e) {
        return;
    }
    
    // create a html for required and optional components
    var reqh = '<h4 class="nc-mt-15">Required parameters</h4>';
    reqh += '<div id="nc-sandbox-required" class="nc-parameters form-horizontal" val="'+funname+'">'        
    var opth = '<h4 class="nc-sandbox-optional">Optional parameters <span><span class="caret"></span></span></h4>';
    opth += '<div id="nc-sandbox-optional" class="nc-parameters form-horizontal">';
    
    // helper function to create a form element for strings, arrays, or data matrices
    var textForm = function(fname, flabel, defvalue) {  
        defvalue = (defvalue===null? '' : defvalue);
        var ans = '<div class="form-group" val="'+fname+'">';                
        ans += '<label class="col-sm-2 control-label">'+flabel+'</label>';
        ans += '<div class="col-sm-7">';
        ans += '<input type="text" class="form-control" placeholder="'+fname+'" value="'+defvalue+'">';    
        ans += '</div></div>';
        return ans;
    }
    var arrayForm = function(fname, flabel, fvalues) {
        var ans ='<div class="form-group" val="'+fname+'">'                
        ans += '<label class="col-sm-2 control-label">'+flabel+'</label>';                
        fvalues = [].concat(fvalues);
        for (var j=0; j<fvalues.length; j++) {
            ans += '<div class="col-sm-1">';
            ans += '<input type="text" class="form-control" value="'+fvalues[j]+'"></div>';
        }
        ans+= '</div>';
        return ans;
    }
    var matrixForm = function(fname, flabel, colnames) {        
        var colspaces = colnames.join(" ");
        var colbreaks = colnames.join("<br/>");
        var ans = '<div class="form-group" val="'+fname+'" colnames="'+colspaces+'">';
        ans += '<label class="col-sm-2 control-label">'+flabel+'<br/><br/>'+colbreaks+'</label>';
        ans += '<div class="col-sm-7"><textarea class="form-control" rows="8"></textarea></div>';                     
        ans += '<div class="col-sm-3 nc-tips">';
        ans += '<p><b>Paste data</b> from a spreadsheet here.</p>';
        ans += '<p>To enter data manually, press the <b>space-bar twice</b> to generate a tab between columns.</p>';
        ans += '</div></div>';
        return ans;            
    }
    
    // loop over the arguments and create form elements
    for (var i=0; i<funargs.length; i++) {
        var iarg = funargs[i];
        var itype = iarg.type.split(":");
        var idesc = iarg.description;
        idesc = idesc[0].toUpperCase() + idesc.slice(1);
        var ivalue = iarg.value;
        var ihtml = "";
        if (itype.length==1 && itype[0]=="string") {
            ihtml = textForm(iarg.name, idesc, ivalue);
        } else if (itype[0]!="array" && itype[0]!="matrix") {
            ihtml = arrayForm(iarg.name, idesc, ivalue);
        } else {            
            ihtml = matrixForm(iarg.name, idesc, itype.slice(1));
        }        
        // append the html to either the required or optional forms        
        if (ivalue===null) {            
            reqh += ihtml;
        } else {            
            opth += ihtml;
        }
    }
        
    // close the required and optional divs
    reqh += '</div>';
    opth += '</div>';
        
    obj.innerHTML = reqh+opth;
}


/* ====================================================================================
 * On page load, activate sandbox converter listener
 * ==================================================================================== */

/**
 * This starts the page initialization code
 */
$(document).ready(
    function () { 
        // apply the conversion function to create a sandbox for a given code block
        if ($('code.sandbox').length!==0) {            
            $('body').html(makealive.convert($('body').html()));
        }

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
   


