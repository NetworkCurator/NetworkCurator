/* 
 * nc-graph.js
 * 
 * Functions that deal with graph display/creation/editing
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.graph = {};


// Some global variables
var nc_force, nc_node, nc_nodes, nc_link, nc_links;
var nc_svg = d3.select("svg");


/* ==========================================================================
 * Handlers for graph editing
 * ========================================================================== */

function ncgAddNode() {
    var point = d3.mouse(this),
    node = {
        x: point[0], 
        y: point[1]
        },
    n = nc_nodes.push(node);
}



/* ==========================================================================
 * Network graph box
 * ========================================================================== */

/**
 * Build a toolbar 
 */
function ncInitGraphToolbar() {
    // check if this is the graph page
    var toolbar = $('#nc-graph-toolbar');
                
    toolbar.append(ncuiMakeDropdownButton("New node:", nc_node_classes, "node"));
    toolbar.append(ncuiMakeDropdownButton("New link:", nc_link_classes, "link"));  
    
    toolbar.find("button").click(function() {
        toolbar.find("button").removeClass("active");  
    })
}

/**
 * This function initializes the behavior of the graph svg. 
 * Uses D3.
 *
 */
function ncInitD3Graph() {
            
    // get the graph svg component
    var jsvg = $('#nc-graph-svg')
    var width = jsvg.css("width");
    var height = jsvg.css("height");
    alert(width+" "+height);
    
    // set d3 svg properties
    nc_svg.attr("width", width);
    nc_svg.attr("height", height);
    alert("qq1");    
    // set d3 force layout
    nc_force = d3.forceSimulation()
    .force("link", d3.forceLink().id(function(d) {
        return d.id;
    }))
    .force("charge", d3.forceManyBody())
    .force("center", d3.forceCenter(width / 2, height / 2))
    .on("tick", ticked);    
    //.nodes([{}]) 
    //.linkDistance(30)
    //.charge(-60);    
    
    
    alert("qq2");
    var newnode = $('#nc-graph-toolbar button[val="node"]');
    var newlink = $('#nc-graph-toolbar button[val="link"]');
    
    // create handlers
    
    jsvg.mouseenter(function() {
        if (newnode.hasClass("active")) {
            jsvg.css("cursor", "crosshair");            
        } else if (newlink.hasClass("active")) {
            jsvg.css("cursor", "crosshair");
        //alert("enter with new link");
        } else {
            jsvg.css("cursor", "auto");
        //alert("enter, but no selection");
        }
    });
      
}


/* ==========================================================================
 * Upon document load
 * ========================================================================== */

$(document).ready(
    function () {
        if ($('#nc-graph').length>0) {
            ncInitGraphToolbar();
            ncInitD3Graph();
        }        
    });