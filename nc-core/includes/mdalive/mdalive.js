/** 
 * mdalive.js
 * 
 * Markdown alive with javascript
 * 
 * Author: Tomasz Konopka
 * 
 */

/**
 * gateway object/namespace for converting md code blocks into javascript "alive" objects
 * 
 */
var mdalive = {};


/**
 * namespace for conversion functions. Functions defined here can be auto-detected
 * upon page load and added to the mdalive.types array.
 * 
 */
mdalive.lib = {};


/**
 * array holding types of conversion functions 
 * 
 */ 
mdalive.types = []; 



/**
 * Auto-detection script that is activated upon page load.
 * Scans elements in mdalive.lib and adds function names to the mdalive.types array.
 * 
 * 
 */
document.addEventListener("DOMContentLoaded", function() {
    mdalive.types = [];
    for (var name in mdalive.lib) {
        if (mdalive.lib.hasOwnProperty(name)) {
            if (typeof mdalive["lib"][name] === "function") {
                mdalive.types.push(name);
            }            
        }
    }     
});



/**
 * Manual reset of the conversion types. After invoking this function, 
 * makeAlive() will not make any changes to its input.
 * 
 */
mdalive.clearTypes = function() {
    mdalive.types = [];
}


/** 
 * Manually add a conversion type and conversion function.
 * This can be useful if a conversion function is not defined through some package and not
 * through the mdalive.lib namespace.
 * 
 * @param name
 * 
 * string code
 * 
 * @param fn
 * 
 * function of structure function(obj, x), where obj is a DOM element and x is 
 * an object with all the settings and data. Each function should document how the
 * relevant data should be encoded therein.
 * 
 */
mdalive.addType = function(name, fn) {    
    this.types.push(name);
    this["lib"][name] = fn;    
}


/**
 * Manually set the conversion types and conversion functions for makeAlive(). 
 * This is equivalent to clearing auto-detected types using clearTypes() and then
 * calling addType() several times.
 *
 * @param typelist
 * 
 * object defining the conversion types. Each element in typelist should be a 
 * key:value pair. 
 *  
 */
mdalive.setTypes = function(typelist) {    
    this.clearTypes();
    for (var key in typelist) {
        if (typelist.hasOwnProperty(key)) {
            this.addType(key, typelist[key]);
        }
    }
}


/**
 * Main function of mdalive.js. Thus turns a static html into an html document with
 * pre-specified and controlled javascript.
 *
 * @param x
 * 
 * string with html. 
 * 
 * The function looks for <pre><code></code></pre> blocks, identifies classes in the
 * <code> elements, and turns the contents into an input of a pre-specified javascript 
 * functions. 
 */
mdalive.makeAlive = function(x) {   
     
    // create a dom element with the desired initial html (does not display)
    var newdiv = document.createElement('div');
    newdiv.innerHTML = x;    
           
    // identify all the <pre><code> blocks, process each one individually
    var allcodes = newdiv.querySelectorAll('pre code.mdalive');    
    for (var i = 0; i < allcodes.length; i++) {        
        var nowcode = allcodes[i];        
        var fn = null;
        // loop through the available chart functions and see if one applies        
        for (var cc = 0; cc<mdalive.types.length; cc++) {            
            var nowtype = mdalive.types[cc];            
            if (nowcode.classList.contains(nowtype)) {
                fn = nowtype;
            }            
        }
        // validate the input
        if (fn != null) {
            // check a proper function is defined
            if (typeof this["lib"][fn]!=="function") {
                nowpre.innerHTML += "\nmdalive error: undeclared function "+fn;  
                fn = null;                
            }            
        }
        if (fn!=null){
            try {                
                var data = JSON.parse(allcodes[i].innerHTML);
            } catch(e) {
                nowpre.innerHTML += "\nmdalive error: cannot parse JSON";
                fn = null;
            }            
        }     
        // try to apply the conversion function
        if (fn !=null) {
            var nowpre = nowcode.parentNode;
            var newobj = document.createElement('div');
            newobj.className += "mdalive";         
            try {
                this["lib"][fn](newobj, data);
                nowpre.parentNode.replaceChild(newobj, nowpre);                                     
            } catch(e) {  
                // report the error into the preview box
                nowpre.innerHTML += "\nmdalive error during conversion: "+e;                    
            }            
        }        
    }    
    
    return newdiv;   
}


/**
 * check if an object x has all the keys required
 * 
 * @param x object with key:value
 * @param req array with strings (required keys)
 * 
 * @return string
 * 
 * If all the required arguments are present, the string will be empty ("").
 * If some parameters are missing, the string will contain their names.
 * 
 */
mdalive.checkArguments = function(x, req) { 
    var result = "";   
    for (var i=0; i<req.length; i++) {
        if (!(req[i] in x)) {
            result += " "+req[i];
        }        
    }   
    return result;
}



/**
 * check object x contains properties set out in defaults. 
 * 
 * @param x object parsed from JSON
 * @param defaults objects holding key:value pairs
 * 
 * @result
 * 
 * if x contains a key defined in defaults, nothing happens and x.key remains unchanged.
 * if x does not contain key, then the object is modified so that x.key = defaults.key
 * 
 * After executing this functions, it is safe to assume that x.key is defined, either
 * explicitly from the original x or through default values set in defaults.
 * 
 */
mdalive.fillArguments = function(x, defaults) {
    for (var key in defaults) {
        if (defaults.hasOwnProperty(key)) {
            // check if x also has this key
            if (!x.hasOwnProperty(key)) {
                x[key] = defaults[key];
            }            
        }
    }
    return x;
}


/* ====================================================================================
 * 
 * End of mdalive.js core. 
 * 
 * Below, append any custom conversion functions using the mdalive.lib namespace
 *
 * ==================================================================================== */


/**
 * Simple barplot using d3 (v4)
 */
mdalive.lib.barplot001 = function(obj, x) {
    
    // check required arguments
    var missing = mdalive.checkArguments(x, ["title", "xlab", "ylab", "names", "values"]);   
    if (missing!="") {
        throw "missing arguments: "+missing;
    }
    
    // add in optional arguments  
    var optional = {
        "size": [200, 200], 
        "margin": [40, 20, 30, 50],
        "fill": []
    };
    mdalive.fillArguments(x, optional);        
    
    // dimensions of entire svg
    var w = +x.size[0];
    var h = +x.size[1];
    // dimensions of the plot area
    var hinner = h-x.margin[0]-x.margin[2];
    var winner = w-x.margin[1]-x.margin[3];
    
    // turns names and values into new structure
    var bardata = [];
    var numbars = x.names.length;
    for (var i=0; i<numbars; i++) {
        bardata.push({
            name: x.names[i], 
            value: x.values[i],
            fill: x.fill[i]
        });
    }
    
    // set the svg space
    d3.select(obj).attr("style", "width: "+w+"px; height: "+h+"px");
           
    // create an svg inside the object
    var svg = d3.select(obj).append("svg")
    .attr("width", w+"px").attr("height", h+"px")    
    .append("g").attr("transform",
        "translate(" + x.margin[3] + "," + x.margin[0] + ")");
                
    // create x-axis and labels 
    svg.append("text").text(x.xlab).style("text-anchor", "middle")
    .attr("y", hinner).attr("dy", "2.5em")
    .attr("x", winner/2);         
    var xscale = d3.scaleBand().range([0, winner]).padding(0.5/numbars)
    .domain(x.names.map(function(d) {
        return d;
    }));
    var xaxis = d3.axisBottom(xscale);    
    svg.append("g").attr("transform", "translate(0," + hinner + ")").call(xaxis)
    .selectAll(".domain, .tick > line").remove();
    
    // create the y axis and labels
    var yscale = d3.scaleLinear()
    .range([hinner, 0])        
    .domain([0, Math.max.apply(null, x.values)]);
    var yaxis = d3.axisLeft(yscale).ticks(4);    
    svg.append("g").call(yaxis);
    svg.append("text").attr("transform", "rotate(-90)")
    .attr("y", 0)
    .attr("x",0 - (h-x.margin[0]-x.margin[2])/2)
    .attr("dy", "-2em")
    .style("text-anchor", "middle")
    .text(x.ylab);
      
    // create tooltips
    var tooltip = d3.select(obj).append("div").style("opacity",0)
    .style("background-color", "#333333").style("border-radius", "4px")
    .style("position", "relative").style("color","#fff4f4").style("padding", "4px")
    .style("font-size", "80%").style("width","40px").style("text-align", "center");    
    
    // functions to show/hide the tooltip div
    var tooltipshow = function(d) {          
        d3.select(this).style("opacity",1);
        tooltip.html("<b>"+d.name+":</b> "+d.value)
        .style("left", (x.margin[3]+xscale(d.name)) + "px")		
        .style("top", -h +(yscale(d.value)) + "px")
        .style("opacity", .9);	
    }
    var tooltiphide = function(d) {
        d3.select(this).style("opacity",0.8);
        tooltip.style("opacity", 0);		        
    }
                    
    // create the barplots rectangles
    svg.selectAll(".bar").data(bardata).enter().append("rect")
    .attr("class", "bar").attr("fill", function(d) {
        return d.fill;
    }).style("opacity", 0.8)
    .attr("x", function(d) {
        return xscale(d.name);
    })
    .attr("width", xscale.bandwidth())
    .attr("y", function(d) {
        return yscale(d.value);
    })
    .attr("height", function(d) {
        return hinner - yscale(d.value);
    })
    .on('mouseover', tooltipshow)
    .on('mouseout', tooltiphide)  
    
    
    // create a title
    svg.append("text").text(x.title).style("text-anchor", "left")
    .attr("y", 0).attr("dy", "-1.5em")
    .attr("x", 0);     
}



/**
 * Simple scatter plot using d3 (v4)
 * 
 */
mdalive.lib.scatterplot001 = function(obj, x) {
    return "from scatterplot";
} 
