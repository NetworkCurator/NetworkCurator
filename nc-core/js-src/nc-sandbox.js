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
// json will be used within a special converter function to hold a temporary network definition
nc.sandbox.json = {};


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

    // helper function converts long text into an array with objects
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
    
    if (req.attr("val")=="preparenetwork") {        
        $('#nc-sandbox-md').hide();    
    }
}


/* ====================================================================================
 * A makealive function to generate sandboxes on-the-fly
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
    var reqh = '';
    var opth = '';
    
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
        
    // create one large html string to place into obj
    var html = '';
    if (reqh!="") {
        html += '<h4 class="nc-mt-15">Required parameters</h4>';
        html += '<div id="nc-sandbox-required" class="nc-parameters form-horizontal" val="'+funname+'">';
        html += reqh +'</div>';
    }
    if (opth!="") {
        html += '<h4 class="nc-sandbox-optional">Optional parameters <span><span class="caret"></span></span></h4>';
        html += '<div id="nc-sandbox-optional" class="nc-parameters form-horizontal">';
        html += opth+'</div>';
    }    
            
    obj.innerHTML = html;
}



/* ====================================================================================
 * A makealive function to validate network definitions
 * ==================================================================================== */

/**
 * makealive conversion function. 
 * 
 * Validates ontology, node, link structure in input object x.
 * Generates output as validation messages a file to download
 * 
 * It is helpful in combination with the sandbox capabilities so that
 * users can copy/paste data into text boxes and then download files that are ready
 * for upload.
 * 
 */
makealive.lib.preparenetwork = function(obj, x) {

    // define accepted arguments
    var xargs = [
    makealive.defArg("name", "string", "Network name", null), 
    makealive.defArg("title", "string", "Network title", null), 
    makealive.defArg("ontology", "array:name:connector:directional", "Ontology", null),
    makealive.defArg("nodes", "array:name:class:title:abstract:content", "Nodes", null),
    makealive.defArg("links", "array:name:class:source:target:title:abstract:content", "Links", null)    
    ];
    
    // provide info on arguments
    if (obj===null) return xargs;        
    
    // check required arguments, check/fill optional arguments
    //makealive.checkArgs(x, xargs);                      
              
    // ***********************************************************************
    // helper functions
    
    // gets non-unique elements in an array
    var notunique = function (arr) { 
        arr.sort();
        var result = [];
        for (var i=0; i<arr.length; i++) {
            if (i>0 && arr[i]==arr[i-1]) {
                result.push(arr[i]);
            }
        }
        return result;
    }
            
    // ***********************************************************************
    // done prep, start processing data

    // clear existing network
    nc.sandbox.json = {};

    var ok = true;
    obj.innerHTML = "Preparing network definition file...<br/>";    
    var err = "<br/><b>ERROR</b> ";

    // validate network name
    if (x.name=="" || x.name.length<2 || nc.utils.checkString(x.name, 1)==0) {
        ok = false;
        obj.innerHTML += err+" Invalid network name: "+JSON.stringify(x.name);
    }
    
    // validate ontology definitions
    var nodeclasses = {};
    var linkclasses = {};
    var fullonto = [];
    if ("ontology" in x) {
        for (var i=0; i<x.ontology.length; i++) {
            var nowonto = x.ontology[i];            
            // check that name exists
            if (nowonto.name == null || nowonto.name=="" || nc.utils.checkString(nowonto.name, 1)==0) {                
                obj.innerHTML+= err+"invalid ontology class name: "+JSON.stringify(nowonto.name);
            } else {                        
                fullonto.push(nowonto.name);
                if (nowonto.connector>0) {
                    linkclasses[nowonto.name] = 1;
                } else {
                    nodeclasses[nowonto.name] = 1;
                }
            }
        }        
        // check for duplicate names
        var ontonotu = notunique(fullonto);
        if (ontonotu.length>0) { 
            ok = false;
            for (var i=0; i<ontonotu.length; i++) {
                obj.innerHTML+= err+"duplicate ontology definitions: "+JSON.stringify(ontonotu[i]);
            }            
        }
    }
                
    // validate node definitions
    var nodenames = {};
    if ("nodes" in x) {
        for (var i=0; i<x.nodes.length; i++) {
            var nownode = x.nodes[i];            
            if (nownode["name"]== null || nownode["name"]=="" || nc.utils.checkString(nownode["name"], 1)==0) {
                ok = false;
                obj.innerHTML += err+"invalide node name: "+JSON.stringify(nownode["name"]);
            } else {
                nodenames[nownode.name] = 1;
            }
            // check that the class exists            
            if (nownode["class"]== null || nownode["class"]=="" || !(nownode["class"] in nodeclasses)) {
                ok = false;
                obj.innerHTML += err+"undefined ontology class: "+JSON.stringify(nownode["class"]);
            } 
        }
    }
    
    // validate link definitions    
    if ("links" in x) {
        for (var i=0; i<x.links.length; i++) {
            var nowlink = x.links[i];
            // check name
            if (nowlink["name"]== null || nowlink["name"]=="" || nc.utils.checkString(nowlink["name"], 1)==0) {
                ok = false;
                obj.innerHTML += err+"invalide node name: "+JSON.stringify(nowlink["name"]);
            }
            // check class
            if (!(nowlink["class"] in linkclasses)) {
                ok = false;
                obj.innerHTML += err+"undefined ontology class: "+JSON.stringify(nowlink["class"]);
            }
            // check source & target nodes
            if (nowlink["source"]==null || !(nowlink["source"] in nodenames)) {
                ok = false;
                obj.innerHTML += err+"undefined source node: "+JSON.stringify(nowlink["source"]);
            }
            if (nowlink["target"] == null || !(nowlink["target"] in nodenames)) {
                ok = false;
                obj.innerHTML += err+"undefined target node: "+JSON.stringify(nowlink["target"]);
            }           
        }
    }
    
    // at the end of the check, provide a button to download a network definition json file    
    if (!ok) {
        return;
    }
        
    // create result object (put it in the global space)
    nc.sandbox.json.network = [];
    var network = {};
    network.name = x.name;
    network.title = x.title;
    nc.sandbox.json.network.push(network);
    nc.sandbox.json.network.title = x.title;
    nc.sandbox.json.ontology = x.ontology;
    nc.sandbox.json.nodes = x.nodes;
    nc.sandbox.json.links = x.links;
        
    obj.innerHTML += 'done<br/><br/>';
    obj.innerHTML += '<a class="btn btn-default" role="button" onclick="javascript:nc.sandbox.downloadJSON()">Download network json file</a>';        
}


// triggers download of nc.sandbox.json 
nc.sandbox.downloadJSON = function() {
    if (!("network" in nc.sandbox.json)) {
        return;
    }
    
    // turn the content of nc.sandbox.json into a string, and trigger download
    var networkdef = JSON.stringify(nc.sandbox.json, null, '  ');
    var filename = nc.sandbox.json.network[0].name+".nc.json";
    nc.utils.saveToFile(networkdef, filename);    
}


/* ====================================================================================
 * network specific makealive functions
 * ==================================================================================== */

/**
 * makealive conversion function. 
 * 
 * Validates ontology, node, link structure in input object x.
 * Generates output as validation messages a file to download
 * 
 * It is helpful in combination with the sandbox capabilities so that
 * users can copy/paste data into text boxes and then download files that are ready
 * for upload.
 * 
 */
makealive.lib.nodeneighbors = function(obj, x) {
    
    // define accepted arguments (network related)
    var xargs = [
    makealive.defArg("network", "string", "Network name", null), 
    makealive.defArg("query", "string", "Node title", null),    
    makealive.defArg("linkclass", "string", "Class of link", null)    
    ];
    
    // get options for venn diagram, then remove some
    var vennargs = makealive.lib.venn01(null, x);    
    vennargs = vennargs.filter(function(x) {
        return x.name!="A" && x.name!="B" && x.name!="title" && x.name!="names";
    });
    
    // merge the two types of arguments
    xargs = xargs.concat(vennargs);
        
    // provide info on arguments
    if (obj===null) return xargs;    
    
    // check required arguments, check/fill optional arguments
    makealive.checkArgs(x, xargs);                      
    
    // do some basic manual checking (avoids sending obviously bad requests to server)
    if (nc.utils.checkString(x.network, 1)+nc.utils.checkString(x.query, 1)<2) {        
        throw "Invalid network or query name";                
    } 
    
    // ***********************************************************************
    // done prep, create chart
        
    // send request for nearest neighbors. When complete, draw a venn02 makealive chart
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "getNeighbors",        
        network: x.network,
        query: x.query,
        linkclass: x.linkclass        
    }, function(data) {            
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            if (data['success']==true) {                                
                x.title = "Neighbors of "+x.query+ " vs. [custom set]";
                x.names = [x.query, "custom set"];
                x.A = data['data'];                
                makealive.lib.venn02(obj, x);                
            } else {                              
                throw "Error: "+data['errormsg'];
            }
        }                             
    }
    );    

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
   


