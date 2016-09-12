/* 
 * nc-graph.js
 * 
 * Functions that deal with graph display/creation/editing
 * 
 * This file mixes jquery and d3 frameworks - perhaps shift entirely to d3?
 * 
 * Helpful blocks:
 * https://bl.ocks.org/curran/9b73eb564c1c8a3d8f3ab207de364bf4
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.graph = {
    nodes: [], // store for raw data on nodes
    links: [], // store for raw data on linke
    sim: {}, // force simulation 
    svg: {}, // the svg div on the page   
    mode: "select"
};


nc.graph.links = [
{
    id: "hello",
    source: "Npw8g4p2z", 
    target: "Nrcszc0zk"
}];
 


/* ==========================================================================
 * Structure of graph box
 * ========================================================================== */

/**
 * Build a toolbar and deal with node/link classes
 */
nc.graph.initToolbar = function() {
        
    // check if this is the graph page
    var toolbar = $('#nc-graph-toolbar');
    nc.graph.svg = d3.select('#nc-graph-svg');   
        
    // get descriptions of the node and link ontology
    var nodes = [], links = [];    
    $.each(nc.ontology.nodes, function(key, val) {       
        nodes.push({
            label: val['class_longname'], 
            val: val['class_name']
        });
    });
    $.each(nc.ontology.links, function(key, val) {        
        links.push({
            label: val['class_longname'], 
            val: val['class_name']
        });
    });
    nodes = nc.utils.sortByKey(nodes, 'label');
    links = nc.utils.sortByKey(links, 'label');
      
    // add ontology options to the new node/new link forms   
    $('#nc-graph-newnode #fg-nodeclass .input-group-btn')
    .append(nc.ui.DropdownButton("", nodes, "node").find('.btn-group'))
    $('#nc-graph-newlink #fg-linkclass .input-group-btn')
    .append(nc.ui.DropdownButton("", links, "link").find('.btn-group'))
    
    // add buttons to the toolbar, finally!
    toolbar.append(nc.ui.ButtonGroup(['Select']));
    toolbar.append(nc.ui.DropdownButton("New node:", nodes, "node"));
    toolbar.append(nc.ui.DropdownButton("New link:", links, "link"));     
    
    toolbar.find("button").click(function() {
        toolbar.find("button").removeClass("active");         
    })    
    
    // create behaviors in the svg based on the toolbar
    
    // create handlers    
    var jsvg = $('#nc-graph-svg');
    jsvg.mouseenter(function() {   
        var newnode = $('#nc-graph-toolbar button[val="node"]');
        var newlink = $('#nc-graph-toolbar button[val="link"]');    
        //var select = $('#nc-graph-toolbar button[val="select"]');        
        if (newnode.hasClass("active")) {
            jsvg.css("cursor", "crosshair");   
            nc.graph.svg.on("click", nc.graph.addNode); 
            nc.graph.mode = "newnode";
        } else if (newlink.hasClass("active")) {
            jsvg.css("cursor", "crosshair");        
            nc.graph.svg.on("click", nc.graph.addLink)            
            nc.graph.mode = "newlink";
        } else {
            jsvg.css("cursor", "move");   
            nc.graph.svg.selectAll('g').attr('cursor', 'default');
            nc.graph.svg.on("click", null);
            nc.graph.svg.selectAll('g').on('click', nc.graph.displayDetails);
            nc.graph.mode = "select";
        }       
    });
  
}



/* ==========================================================================
 * Handlers for graph editing
 * ========================================================================== */

nc.graph.addNode = function() {
    // find the current node type, then add a classed node    
    var nodeclass = d3.select('#nc-graph-toolbar button[val="node"]').attr("selection");
    nc.graph.addClassedNode(d3.mouse(this), nodeclass, "nc-newnode");            
}

nc.graph.addClassedNode = function(point, nodeclass, styleclass) {      
    var newid = "_"+nodeclass+"_"+Date.now(),    
    newnode = {
        "id": newid,
        "name": newid,
        "class_name": nodeclass,
        "class": styleclass,
        x: point[0], 
        y: point[1],
        fx: point[0], 
        fy: point[1]
    };           
    nc.graph.simStop();
    nc.graph.nodes.push(newnode);          
    nc.graph.initSimulation();    
}


nc.graph.addLink = function() {
    var linkclass = d3.select('#nc-graph-toolbar button[val="link"]').attr("selection");
    nc.graph.addClassedLink(linkclass, "nc-newlink");
}

nc.graph.addClassedLink = function(linkclass, styleclass) {   
   
    // identify the temporary link
    var draggedlink = nc.graph.svg.select('line[class="nc-draggedlink"]');       
    // create a new entity for the links array
    var newid = "_"+linkclass+"_"+Date.now();
    var newlink = {
        "id": newid,
        "class_name": linkclass,
        "class": styleclass,
        "source": draggedlink.attr("source"),
        "target": draggedlink.attr("target")       
    }
    //alert("newlink: "+JSON.stringify(newlink));
    // update the simulation
    nc.graph.links.push(newlink);
    nc.graph.initSimulation();
}


/**
 * Run when user clicks on a node or link in the graph
 */
nc.graph.select = function(d) {
    
    if (nc.graph.mode!="select") {
        return;
    }
    
    // un-highlight existing
    nc.graph.svg.selectAll('circle').classed('nc-node-highlight',false);
    nc.graph.svg.selectAll('line').classed('nc-link-highlight',false);       
           
    var nowid = d.id;    
    if (nowid[0]=="_") {
        $('#nc-graph-details').hide();   
        if ("source" in d) {
            alert(JSON.stringify(d));
            var newlinkdiv = $('#nc-graph-newlink');
            // selected link            
            newlinkdiv.show();
            $('#nc-graph-newnode').hide();                                    
            nc.graph.svg.select('line[id="'+d.id+'"]').classed('nc-link-highlight', true);            
            // transfer the temporary link id into the create box 
            $('#nc-graph-newlink #fg-linkname input').val(nowid);
            $('#nc-graph-newlink #fg-linktitle input').val(nowid);
            // set the dropdown with the class
            newlinkdiv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newlinkdiv.find('button.dropdown-toggle').attr('val', d.class_name);
            // set the source and target text boxes
            $('#nc-graph-newlink #fg-linksource input').val(d.source.name);
            $('#nc-graph-newlink #fg-linktarget input').val(d.target.name);
        } else {
            // selected node
            $('#nc-graph-newlink').hide();
            var newnodediv = $('#nc-graph-newnode')
            newnodediv.show(); 
            // highlight the selected node            
            nc.graph.svg.select('circle[id="'+nowid+'"]').classed('nc-node-highlight', true);            
            // transfer the temporary node id into the create box
            $('#nc-graph-newnode #fg-nodename input').val(nowid);
            $('#nc-graph-newnode #fg-nodetitle input').val(nowid);
            $('#nc-graph-newnode form').attr('val', nowid);
            // set the dropdown with the class
            newnodediv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newnodediv.find('button.dropdown-toggle').attr('val', d.class_name);        
        }    
    } else {        
        $('#nc-graph-details').show();
        $('#nc-graph-newlink,#nc-graph-newnode').hide();   
        if ("source" in d) {
            nc.graph.svg.select('line[id="'+d.id+'"]').classed('nc-link-highlight', true);
        } else {
            nc.graph.svg.select('circle[id="'+d.id+'"]').classed('nc-node-highlight', true);
        }
    }
                    
}

/* ==========================================================================
* Handlers for graph editing
* ========================================================================== */


/**
* This function initializes the behavior of the graph svg simulation.
* 
* Uses D3.
*
*/
nc.graph.initSimulation = function() {
        
    // set up zooming
         
    // get the graph svg component
    nc.graph.svg = d3.select('#nc-graph-svg'); //.call("zoom");
   
    var width = nc.graph.svg.style("width").replace("px","");    
    var height = nc.graph.svg.style("height").replace("px", "");    
                
    // create new simulation    
    nc.graph.sim = d3.forceSimulation()
    .force("link", d3.forceLink().id(function(d) {
        return d.id;
    }))
    .force("charge", d3.forceManyBody())
    .force("center", d3.forceCenter(width / 2, height / 2))
    .velocityDecay(0.6);    
        
    if (nc.graph.nodes.length>0) {
        nc.graph.simStart();
    }
}


/**
* add elements into the graph svg and run the simulation
* 
*/
nc.graph.simStart = function() {
    
    // first clear the existing svg if it already contains elements
    nc.graph.svg.selectAll("g").remove();
    
    // set up node draggin
    var nodedrag = d3.drag()
    .on("start", nc.graph.dragstarted)
    .on("drag", nc.graph.dragged)
    .on("end", nc.graph.dragended);
    
    // create var with set of links (used in the tick function)
    var link = nc.graph.svg.append("g")
    .attr("class", "links")
    .selectAll("line")
    .data(nc.graph.links)
    .enter().append("line")
    .attr("class", function(d) {
        if ("class" in d) {
            return d["class"];
        } else {
            return "nc-default-link";
        }
    }) 
    .attr("id", function(d) {
        return d.id;
    })
    .on("click", nc.graph.select);                    
    
    // create a var with a set of nodes (used in the tick function)
    var node = nc.graph.svg.append("g")
    .attr("class", "nodes")
    .selectAll("circle")
    .data(nc.graph.nodes)
    .enter().append("circle")
    .attr("r", 9)
    .attr("id", function(d) {        
        return d.id;
    })
    .attr("class",function(d) {
        if ("class" in d) {
            return "nc-default-node "+d["class"];
        } else {
            return "nc-default-node";
        }
    })
    .call(nodedrag).on("click", nc.graph.select);
        
    
    // performed at each simulation step to reposition the nodes and links
    var tick = function() {        
        link.attr("x1", dsourcex).attr("y1", dsourcey).attr("x2", dtargetx).attr("y2", dtargety);    
        node.attr("cx", dx).attr("cy", dy);    
    }
    
    nc.graph.sim.nodes(nc.graph.nodes).on("tick", tick);                   
    nc.graph.sim.force("link").links(nc.graph.links);          
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
    nc.graph.sim.restart();
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


/**
* @param d the object that is being dragged
*/
nc.graph.dragstarted = function(d) {   
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.3).restart();    
            break;
        case "newlink":
            nc.graph.simStop(); 
            nc.graph.svg.select('circle[id="'+d.id+'"]').classed("nc-newlink-source", true);
            nc.graph.svg.select('g[class="links"]').append("line")
            .attr('source', d.id)
            .attr('class', 'nc-draggedlink').attr("x1", d.x).attr("y1", d.y).attr("x2", d.x+20).attr("y2", d.y+20);            
            break;
        default:
            break;
    }
}

/**
* @param d the object that is being dragged 
*/
nc.graph.dragged = function(d) {     
    var point = d3.mouse(this);    
    switch (nc.graph.mode) {
        case "select":
            var dindex = d.index;                
            nc.graph.nodes[dindex].fx = point[0];
            nc.graph.nodes[dindex].fy = point[1];           
            break;
        case "newlink":
            var besttarget = nc.graph.findNearestNode(point);            
            nc.graph.svg.select('line[class="nc-draggedlink"]')
            .attr('target', besttarget.id)
            .attr("x2",besttarget.x).attr("y2", besttarget.y);            
            var newtarget = nc.graph.svg.select('#'+besttarget.id);
            if (!newtarget.classed("nc-newlink-target")) {
                nc.graph.svg.selectAll('circle').classed('nc-newlink-target', false);
                newtarget.classed('nc-newlink-target', true);
            }
            break;
        default:
            break;
    }    
}

nc.graph.dragended = function(d) {
    var point = d3.mouse(this);    
    var dindex = d.index;    
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.0);
            nc.graph.nodes[dindex].fx = null;
            nc.graph.nodes[dindex].fy = null;    
            break;
        case "newlink":
            nc.graph.addLink();
            break;
        case "default":
            break;
    }
    
    
}

nc.graph.zoom = function() {
    nc.graph.svg.attr("transform", 
        "translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
}


/* ==========================================================================
* Helper functions
* ========================================================================== */

/**
 * Find the node that is nearest to the given point
 */
nc.graph.findNearestNode = function(p) {
    var bestid = 0;
    var bestd = Infinity;
    for (var i=0; i<nc.graph.nodes.length; i++) {
        var nowd = Math.pow(nc.graph.nodes[i].x - p[0], 2) + Math.pow(nc.graph.nodes[i].y - p[1], 2);
        if (nowd<bestd) {
            bestd = nowd;
            bestid = i;
        }
    }
    return nc.graph.nodes[bestid];
}


/**
 * Replace an existing node id by a new one.
 * Returns the index of the node in the nc.graph.nodes array
 */
nc.graph.replaceNodeId = function(oldid, newid) {
    
    var i;
    // replace any linking data
    var llen = nc.graph.links.length;
    for (i=0; i<llen; i++) {
        if (nc.graph.links[i].source==oldid) {
            nc.graph.links[i].source = newid;
        }
        if (nc.graph.links[i].target==oldid) {
            nc.graph.links[i].target = newid;
        }
    }
    // replace the id in the nodes array
    var nlen = nc.graph.nodes.length;
    for (i=0; i<nlen; i++) {
        if (nc.graph.nodes[i].id==oldid) {
            nc.graph.nodes[i].id = newid;
            return i;
        }
    }
    
    return -1;
}


/**
 * Replaces an id in the nc.graph.links array
 * Returns the index to the link in question
 */
nc.graph.replaceLinkId = function(oldid, newid) {     
    // replace any linking data
    var llen = nc.graph.links.length;
    for (var i=0; i<llen; i++) {
        if (nc.graph.links[i].id==oldid) {
            nc.graph.links[i].id = newid;
            return i;
        }        
    }
    return -1;
}

/* ==========================================================================
* Communicating with server
* ========================================================================== */


nc.graph.createNode = function() {
    
    var allfg = '#fg-nodename,#fg-nodetitle,#fg-nodeclass';
    $(allfg).removeClass('has-warning has-error');
    // basic checks on the network name text box    
    if (nc.utils.checkFormInput('fg-nodename', "node name", 1)+
        nc.utils.checkFormInput('fg-nodetitle', "node title", 2) < 2) return 0;    
   
    var oldid = $('#nc-graph-newnode form').attr('val');
    var newname = $('#fg-nodename input').val();
    var newclass = $('#fg-nodeclass button.dropdown-toggle').attr('selection');
   
    // give feedback on the form that a request is being sent
    $('#nc-graph-newnode button.submit')
    .removeClass('btn-success').addClass("btn-default")
    .html("Sending...").attr("disabled", true); 
   
    // send a request to create node
    // post the registration request 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "createNewNode", 
        network_name: nc.network,
        node_name: newname,
        node_title: $('#fg-nodetitle input').val(),
        class_name: newclass
    }, function(data) {          
        nc.utils.alert(data);        
        //alert(data);
        data = $.parseJSON(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                $('#fg-nodename').addClass('has-error has-feedback');                
                $('#fg-nodename label').html("Please choose another node name:");                
            } else if (data['success']==true) { 
                //alert("exchaging "+oldid+" "+data['data']);
                $(allfg).addClass('has-success has-feedback');                                
                $('form button.submit').removeClass('btn-success').addClass('btn-default disabled').html("Success!");                
                $('form,form button.submit').attr("disabled", true);    
                // replace the node id in from the temporary one to the real one
                var nodeindex = nc.graph.replaceNodeId(oldid, data['data']);
                nc.graph.nodes[nodeindex].name = newname;
                nc.graph.nodes[nodeindex]["class"] = newclass;
                nc.graph.svg.select('circle[id="'+oldid+'"]')
                .attr("id", data['data'])
                .classed('nc-newnode', false).classed('nc-node-highlight', false).
                classed(newclass, true).classed('nc-node-highlight', true);                
            }
        }                 
        // make user wait a little
        setTimeout(function() {
            $('#nc-graph-newnode button.submit')
            .addClass('btn-success').removeClass("btn-default")
            .html("Create").attr("disabled", false); 
        }, nc.ui.timeout/4);
        
    //alert("after nodes: "+JSON.stringify(nc.graph.nodes));
    }
    );    
}


nc.graph.createLink = function() {
    
    }
