/* 
 * nc-graphsim.js
 * 
 * Functions that deal with graph simulation.
 * Functions and functionality are closely coupled with nc-graph.js. Functions assume
 * that several objects in the nc.graph namespace are defined, e.g. nc.graph.rawnodes 
 * and nc.graph.rawlinks as stores of all nodes and links 
 *  
 * 
 * Helpful blocks:
 * https://bl.ocks.org/curran/9b73eb564c1c8a3d8f3ab207de364bf4
 * 
 */


/* ====================================================================================
 * Graph simulation display
 * ==================================================================================== */

/**
 * Extract the current transform values in the graph svg
 */
nc.graph.getTransformation = function() {        
    var content = nc.graph.svg.select("g.nc-svg-content");     
    if (content.empty()) {        
        return [0,0,1];
    }
    var trans = content.attr("transform").split(/\(|,|\)/);            
    return [parseFloat(trans[1]), parseFloat(trans[2]), parseFloat(trans[4])];          
}

/**
 * This function initializes the behavior of the graph svg simulation (force layout)
 * and some core svg components like zoom and pan.
 * 
 * Uses D3.
 *
 */
nc.graph.initSimulation = function() {
                    
    // get the graph svg component
    nc.graph.svg = d3.select('#nc-graph-svg'); 
    // fetch the existing transform and scale, if any
    var initT = nc.graph.getTransformation();
    // reset the svg    
    nc.graph.svg.selectAll('defs,g,rect,text').remove();
          
    // create default definitions for objects
    var newnode = '<circle id="nc-newnode" cx=0 cy=0 r=9></circle>';    
    // create default styles (ncstyle0 for defaults, ncstyle1 for high-priority style)
    var ncstyles0 = 'use.nc-node { fill: #449944; }\n';
    ncstyles0 += 'line.nc-link { stroke: #999999; stroke-width: 5; }\n';    
    ncstyles0 += 'use.nc-newnode { stroke: #000000; stroke-width: 2; stroke-dasharray: 3 3; fill: #dddddd; }\n';
    ncstyles0 += 'line.nc-newlink, line.nc-draggedlink { stroke: #aaaaaa; stroke-width: 6; stroke-dasharray: 7 5; }\n';
    ncstyles0 += 'text { text-anchor: middle; }\n';
    ncstyles0 += 'text.tooltip { text-anchor: start; }\n';        
    var ncstyles1 = 'use.nc-inactive { stroke: #777777; stroke-width: 5;  stroke-dasharray: 3 3; }\n';       
    ncstyles1 += 'line.nc-inactive { stroke: #777777; stroke-width: 5;  stroke-dasharray: 3 3; }\n';    
    ncstyles1 += 'line.nc-link-highlight { stroke: #000000; stroke-width: 4; }\n';
    ncstyles1 += 'use.nc-node-highlight { stroke: #000000; stroke-width: 4; }\n';
    ncstyles1 += 'use.nc-node-center { stroke: #000000; stroke-width: 4;  stroke-dasharray: 5 3; }\n';    
        
    // add ontology definitions as defs    
    var temp = $.map($.extend({}, nc.ontology.nodes, nc.ontology.links), function(value) {
        return [value];
    });    
    var newstyles = "";
    var nonstyles = "";
    for (var i=0; i<temp.length; i++) {     
        var nowdef = $('<div>').html(temp[i].defs)
        // fetch the style definition, minify the whitespace
        var nowstyle = nowdef.find('style').html().replace(/\s+/g, ' ').replace(/^\s/,'');
        if (nowstyle !== undefined) {
            newstyles += nowstyle+'\n';
            nowdef.find('style').remove();            
        } 
        nonstyles += nowdef.html();                
    }    
    nc.graph.svg.append("defs").html(nonstyles+newnode);
    nc.graph.svg.append("defs").html('<style type="text/css">'+ncstyles0+newstyles+ncstyles1+'</style>');
    
      
    var width = parseInt(nc.graph.svg.style("width"));    
    var height = parseInt(nc.graph.svg.style("height"));          
             
    // create new simulation    
    nc.graph.sim = d3.forceSimulation()
    .force("link", d3.forceLink().distance(nc.graph.settings.linklength).id(function(d) {
        return d.id;
    }))    
    .force("charge", d3.forceManyBody().strength(nc.graph.settings.strength).distanceMax(400))
    .force("center", d3.forceCenter(width / 2, height / 2))
    .velocityDecay(nc.graph.settings.vdecay);    
                                                
    // Set up panning and zoom (uses a rect to catch click-drag events)                        
    var svgpan = d3.drag().on("start", nc.graph.panstarted).
    on("drag", nc.graph.panned).on("end", nc.graph.panended);       
    nc.graph.zoombehavior = d3.zoom().scaleExtent([0.125, 4])   
    .on("zoom", nc.graph.zoom);  
        
    nc.graph.svg.append("rect").classed("nc-svg-background", true)
    .attr("width", "100%").attr("height", "100%")
    .style("fill", "none").style("pointer-events", "all")    
    .call(svgpan).call(nc.graph.zoombehavior)
    .on("wheel", function() {         
        d3.event.preventDefault();
    })
    .on("click", nc.graph.unselect);   
    
    // create a single group g for holding all nodes and links
    nc.graph.svg.append("g").classed("nc-svg-content", true)
    .attr("transform", "translate("+initT[0]+","+initT[1]+")scale("+initT[2]+")");                                                

    if (nc.graph.rawnodes.length>0) {
        nc.graph.simStart();
    }    
}

// update function for force layout
nc.graph.tick = function() {
    // performed at each simulation step to reposition the nodes and links    
    nc.graph.svglinks.attr("x1", dsourcex).attr("y1", dsourcey).attr("x2", dtargetx).attr("y2", dtargety);    
    nc.graph.svgnodes.attr("x", dx).attr("y", dy);    
    nc.graph.svgnodetext.attr("x", dx).attr("y", dy);        
}

/**
 * Add elements into the graph svg and run the simulation
 * (invoked whenever the simulation needs to be refreshed)
 * 
 */
nc.graph.simStart = function() {
    
    // first clear the existing svg if it already contains elements
    nc.graph.svg.selectAll("g.links,g.nodes,text,g.nodetext").remove();
    nc.graph.sim.alpha(0);
        
    // filter the raw node and raw links    
    nc.graph.filterNodes();        
    nc.graph.filterLinks();    
    nc.graph.filterNeighborhood();
                        
    // set up node dragging
    var nodedrag = d3.drag().on("start", nc.graph.dragstarted)
    .on("drag", nc.graph.dragged).on("end", nc.graph.dragended);
                     
    // create var with set of links (used in the tick function)
    nc.graph.svglinks = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "links")
    .selectAll("line")
    .data(nc.graph.links)
    .enter().append("line")
    .attr("class", function(d) {        
        return "nc-link "+d["class"] + (d["status"]<1 ? " nc-inactive": "");        
    })   
    .attr("id", function(d) {
        return d.id;
    })
    .on("click", nc.graph.selectObject);                    
     
    // create a tooltip area
    var tooltip = nc.graph.svg.select("g.nc-svg-content")
    .append("text").classed("tooltip", true).attr("id", "nc-svg-tooltip");            
    
    // functions to show/hide the tooltip div
    var tooltipshow = function(d) {    
        if (nc.graph.settings.tooltip) {
            var mp = d3.mouse(this);         
            // fetch transformation (allows to undo scaling)
            var nowtrans = nc.graph.getTransformation();
            var nowscale = Math.round(100/nowtrans[2]);
            tooltip.text(d.name).attr("x", +mp[0]+2).attr("y", +mp[1])                           
            .style('display', 'inline-block').style('font-size', nowscale+'%');            
        }
    }
    var tooltiphide = function(d) {        
        tooltip.style('display', 'none');		        
    }
    
    // create a var with a set of nodes (used in the tick function)
    nc.graph.svgnodes = nc.graph.svg.select("g.nc-svg-content").insert("g","text")
    .attr("class", "nodes")
    .selectAll("use").data(nc.graph.nodes).enter().append("use")
    .attr("xlink:href", function(d) {
        return "#"+d['class'];
    })
    .attr("id", function(d) {        
        return d.id;
    })
    .attr("class",function(d) {        
        return "nc-node "+d["class"]+ (d["status"]<1 ? " nc-inactive": "");        
    })
    .call(nodedrag).on("click", nc.graph.selectObject).on("dblclick", nc.graph.toggleSearchNode)
    .on('mouseover', tooltipshow).on('mouseout', tooltiphide);  
                  
    // create textlabels for nodes (depending on node class settings)
    var textnodes = nc.graph.nodes.filter(function(z) {         
        var zcid = z.class_id;        
        return nc.ontology.nodes[zcid].showlabel>0;        
    });
    nc.graph.svgnodetext = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "nodetext")
    .selectAll("text").data(textnodes).enter().append("text")
    .text(function(d) {
        return d.name
    })
    .attr("dx", "0em").attr("dy", "0.3em")
    .style("font-size", nc.graph.settings.namesize+"pt")    
    .call(nodedrag)
    .on('click', nc.graph.selectObject ).on("dblclick", nc.graph.toggleSearchNode);    
         
    nc.graph.sim.nodes(nc.graph.nodes).on("tick", nc.graph.tick);                   
    nc.graph.sim.force("link").links(nc.graph.links);         
    
    // add a selection class to one of the nodes    
    nc.graph.settings.searchnodes = nc.graph.getSearchElements();
    // find the ids that corresponds to the search nodes
    for (var i=0; i<nc.graph.nodes.length; i++) {
        var v = nc.graph.nodes[i];
        if (nc.graph.settings.searchnodes.indexOf(v.name)>=0) {
            nc.graph.svg.select('use[id="'+v.id+'"]').classed("nc-node-center", true);           
        }
    }                   
    
    nc.graph.simUnpause();    
}


/**
 * Stop the simulation
 */
nc.graph.simStop = function() {
    nc.graph.sim.stop();
}

/**
 * Start/unpause the simulation
 */
nc.graph.simUnpause = function() {    
    nc.graph.sim.alpha(0.7).restart();    
}

var dsourcex = function(d) {
    return d.source.x;    
}
var dsourcey = function(d) {
    return d.source.y;
};
var dtargetx = function(d) {
    return d.target.x;
};
var dtargety = function(d) {
    return d.target.y;
};
var dx = function(d) {
    return d.x;
}
var dy = function(d) {
    return d.y;
}
