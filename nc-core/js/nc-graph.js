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
    rawnodes: [], // store for raw data on nodes
    rawlinks: [], // store for raw data on links
    nodes: [], // store active nodes in the viz
    links: [], // store active links in the viz
    sim: {}, // force simulation 
    svg: {}, // the svg div on the page   
    mode: "select"
};


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
    var pushonto = function(x) {
        var result = [];
        $.each(x, function(key, val) {            
            result.push({
                id: val['class_id'],
                label: val['class_longname'], 
                val: val['class_name'],
                status: val['class_status']
            });
        });        
        return nc.utils.sortByKey(result, 'label');
    }    
    var nodes=pushonto(nc.ontology.nodes), links=pushonto(nc.ontology.links);
     
    // add ontology options to the new node/new link forms   
    $('#nc-graph-newnode #fg-nodeclass .input-group-btn')
    .append(nc.ui.DropdownButton("", nodes, "node", false).find('.btn-group'))
    $('#nc-graph-newlink #fg-linkclass .input-group-btn')
    .append(nc.ui.DropdownButton("", links, "link", false).find('.btn-group'))
    
    // add buttons to the toolbar, finally!
    toolbar.append(nc.ui.ButtonGroup(['Select']));
    toolbar.append(nc.ui.DropdownButton("New node:", nodes, "node", false));
    toolbar.append(nc.ui.DropdownButton("New link:", links, "link", false));     
    
    toolbar.find("button").click(function() {
        toolbar.find("button").removeClass("active");         
    })    
    
    // create behaviors in the svg based on the toolbar
    
    // create handlers    
    var jsvg = $('#nc-graph-svg');
    jsvg.mouseenter(function() {   
        var newnode = $('#nc-graph-toolbar button[val="node"]');
        var newlink = $('#nc-graph-toolbar button[val="link"]');            
        if (newnode.hasClass("active")) {
            jsvg.css("cursor", "crosshair");   
            nc.graph.svg.on("click", nc.graph.addNode); 
            nc.graph.mode = "newnode";
        } else if (newlink.hasClass("active")) {
            jsvg.css("cursor", "crosshair");        
            nc.graph.svg.on("click", nc.graph.addLink)            
            nc.graph.mode = "newlink";
        } else {
            jsvg.css("cursor", "grab");   
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
    var whichnode = d3.select('#nc-graph-toolbar button[val="node"]');
    var classid = whichnode.attr('class_id');
    var classname = whichnode.attr('class_name');
    nc.graph.addClassedNode(d3.mouse(this), classid, classname, "nc-newnode");            
}

nc.graph.addClassedNode = function(point, classid, classname, styleclass) {             
    var newid = "+"+classname+"_"+Date.now(),
    newnode = {
        "id": newid,
        "name": newid,
        "class_id": classid,
        "class_name": classname,
        "class": styleclass,
        "class_status": 1,
        x: point[0], 
        y: point[1],
        fx: point[0], 
        fy: point[1]
    };    
    //alert(JSON.stringify(newnode));
    nc.graph.simStop();
    nc.graph.rawnodes.push(newnode);          
    nc.graph.initSimulation();    
}


nc.graph.addLink = function() {
    var whichlink = d3.select('#nc-graph-toolbar button[val="link"]');    
    var classid = whichlink.attr("class_id");
    var classname =  whichlink.attr("class_name");
    return nc.graph.addClassedLink(classid, classname, "nc-newlink");
}

nc.graph.addClassedLink = function(classid, classname, styleclass) {   
   
    // identify the temporary link
    var draggedlink = nc.graph.svg.select('line[class="nc-draggedlink"]');       
    
    // check that the link is proper
    if (draggedlink.attr("source")===null || draggedlink.attr("target")===null) {
        return null;
    }
    
    // create a new entity for the links array
    var newid = "+"+classname+"_"+Date.now();
    var newlink = {
        "id": newid,
        "class_id": classid,
        "class_name": classname,
        "class_status": 1,
        "class": styleclass,
        "source": draggedlink.attr("source"),
        "target": draggedlink.attr("target")       
    }
    
    // update the simulation
    nc.graph.simStop();
    nc.graph.rawlinks.push(newlink);
    nc.graph.initSimulation();
    
    return newlink;
}


/**
 * Run when user clicks on a node or link in the graph
 */
nc.graph.select = function(d) {
          
    if (d==null) {
        return;
    }

    // un-highlight existing, then highlight required id
    nc.graph.unselect();    
    if ("source" in d) {
        nc.graph.svg.select('line[id="'+d.id+'"]').classed('nc-link-highlight', true);
    } else {
        nc.graph.svg.select('circle[id="'+d.id+'"]').classed('nc-node-highlight', true);
    }    
        
    var nowid = d.id;    
    if (nowid[0]=="+") {
        $('#nc-graph-details').hide();  
        nc.graph.resetForms();
        if ("source" in d) {            
            // selected a link  
            var newlinkdiv = $('#nc-graph-newlink');                                              
            // transfer the temporary link id into the create box 
            $('#nc-graph-newlink #fg-linkname input').val(nowid);
            $('#nc-graph-newlink #fg-linktitle input').val(nowid);
            $('#nc-graph-newlink form').attr('val', nowid);
            // set the dropdown with the class
            newlinkdiv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newlinkdiv.find('button.dropdown-toggle').attr('selection', d.class_name);
            // set the source and target text boxes
            $('#nc-graph-newlink #fg-linksource input').val(d.source.name);
            $('#nc-graph-newlink #fg-linktarget input').val(d.target.name);
            newlinkdiv.show();
        } else {
            // selected a node            
            var newnodediv = $('#nc-graph-newnode')            
            // transfer the temporary node id into the create box
            $('#nc-graph-newnode #fg-nodename input').val(nowid);
            $('#nc-graph-newnode #fg-nodetitle input').val(nowid);
            $('#nc-graph-newnode form').attr('val', nowid);
            // set the dropdown with the class
            newnodediv.find('button.dropdown-toggle span.nc-classname-span').html(d.class_name);
            newnodediv.find('button.dropdown-toggle').attr('selection', d.class_name);        
            newnodediv.show(); 
        }    
    } else {        
        $('#nc-graph-details').show();        
    }
                    
}

/**
 * Remove all highlight styling from components in the graph
 */
nc.graph.unselect = function(d) {
    nc.graph.svg.selectAll('circle').classed('nc-node-highlight',false);
    nc.graph.svg.selectAll('line').classed('nc-link-highlight',false);  
    $('#nc-graph-newnode,#nc-graph-newlink,#nc-graph-details').hide();    
}


/* ==========================================================================
 * Node/Link filtering
 * ========================================================================== */


nc.graph.filterNodes = function() {         
        
    // convenience shallow copies of nc.graph objects
    var rawnodes = nc.graph.rawnodes;
    var nonto = nc.ontology.nodes;    
    var nlen = rawnodes.length;
    
    // loop and deep copy certain nodes from raw to new
    var counter = 0;
    nc.graph.nodes = [];
    for (var i=0; i<nlen; i++) {
        // get input class id
        var iclassid = rawnodes[i].class_id;    
        if (nonto[iclassid].class_status>0) {
            nc.graph.nodes[counter] = rawnodes[i];
            counter++;
        }        
    }
    
}

nc.graph.filterLinks = function() {
    //alert("filtering links "+nc.graph.rawlinks.length);
    
    // some shorthand objects
    var rawlinks = nc.graph.rawlinks;
    var llen = rawlinks.length;
    var lonto = nc.ontology.links;

    // get an array of all available nodes
    var goodnodes = {};
    for (var j=0; j<nc.graph.nodes.length; j++) {
        goodnodes[nc.graph.nodes[j].id]= 1;
    }
    //alert(JSON.stringify(goodnodes));

    var counter = 0;
    nc.graph.links = [];    
    //alert(nc.graph.links.length+" "+nc.graph.rawlinks.length);
    for (var i=0; i<llen; i++) {
        var iclassid = rawlinks[i].class_id;        
        if (lonto[iclassid].class_status>0) {
            // must also check if source and end nodes should be displayed            
            var isource = rawlinks[i].source;
            var itarget = rawlinks[i].target;
            if ((isource in goodnodes || isource.id in goodnodes) && 
                (itarget in goodnodes || itarget.id in goodnodes)) {                                
                nc.graph.links[counter] = nc.graph.rawlinks[i];
                counter++;
            }                                    
        }
    }

//alert("started with "+llen+" filtered to "+nc.graph.links.length);
}


/* ==========================================================================
 * Graph display
 * ========================================================================== */


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
    nc.graph.svg.selectAll('g,rect').remove();
   
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
            
    // create a rect in the svg that will help with panning                       
    var svgpan = d3.drag().on("start", nc.graph.panstarted).
    on("drag", nc.graph.panned).on("end", nc.graph.panended);       
    
    nc.graph.svg.append("rect").classed("nc-svg-background", true)
    .attr("width", "100%").attr("height", "100%")
    .style("fill", "none").style("pointer-events", "all")    
    .call(svgpan).on("click", nc.graph.unselect);    
      
    // create a single group g for holding all nodes and links
    nc.graph.svg.append("g").classed("nc-svg-content", true)
    .attr("transform", "translate(0,0)scale(1)");            
            
    if (nc.graph.rawnodes.length>0) {
        nc.graph.simStart();
    }
}


/**
 * add elements into the graph svg and run the simulation
 * 
 */
nc.graph.simStart = function() {
    
    // first clear the existing svg if it already contains elements
    nc.graph.svg.selectAll("g.links,g.nodes").remove();
    nc.graph.sim.alpha(0);
    
    // filter the raw node and raw links
    nc.graph.filterNodes();
    nc.graph.filterLinks();
    
    //nc.graph.nodes = nc.graph.rawnodes;
    //nc.graph.links = nc.graph.rawlinks;
    
    //alert(JSON.stringify(nc.graph.nodes));
    
    //alert("sisisi");
    //alert(" ss "+nc.graph.nodes.length+" "+nc.graph.links.length+" aa");
    
    // set up node dragging
    var nodedrag = d3.drag().on("start", nc.graph.dragstarted)
    .on("drag", nc.graph.dragged).on("end", nc.graph.dragended);
                     
    // create var with set of links (used in the tick function)
    var link = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "links")
    .selectAll("line")
    .data(nc.graph.links)
    .enter().append("line")
    .attr("class", function(d) {
        if ("class" in d) {
            return "nc-default-link "+d["class_name"]+" "+d["class"];
        } else {
            return "nc-default-link "+d["class_name"];
        }
    }) 
    .attr("id", function(d) {
        return d.id;
    })
    .on("click", nc.graph.select);                    
    
    // create a var with a set of nodes (used in the tick function)
    var node = nc.graph.svg.select("g.nc-svg-content").append("g")
    .attr("class", "nodes")
    .selectAll("circle")
    .data(nc.graph.nodes)
    .enter().append("circle").attr("r", 9)    
    .attr("id", function(d) {        
        return d.id;
    })
    .attr("class",function(d) {
        if ("class" in d) {
            return "nc-default-node "+d["class_name"]+" "+d["class"];
        } else {
            return "nc-default-node "+d["class_name"];
        }
    })
    .call(nodedrag).on("click", nc.graph.select).on("dblclick", nc.graph.unpin);        
    
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
    //alert("unpausing");
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


/* ==========================================================================
 * Interactions (dragging, panning, zooming)
 * ========================================================================== */


/**
 * Unpin the position of a node
 */
nc.graph.unpin = function(d) {    
    if ("index" in d) {
        nc.graph.nodes[d.index].fx = null;
        nc.graph.nodes[d.index].fy = null;                   
    }
}


/**
 * @param d the object (node) that is being dragged
 */
nc.graph.dragstarted = function(d) {  
    
    // pass on the event to "select" - this will highlights the dragged object
    nc.graph.select(d);
    
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
 * @param d the object (node) that is being dragged 
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

/**
 * @param d the object (node) that was dragged
 */
nc.graph.dragended = function(d) {    
    var dindex = d.index;    
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.0);
            nc.graph.nodes[dindex].fx = null;
            nc.graph.nodes[dindex].fy = null;    
            break;
        case "newlink":
            var newlink = nc.graph.addLink(); // this is here to make links respond to drag events            
            nc.graph.select(newlink);
            break;
        case "default":
            break;
    }        
}

// for panning it helps to keep track of the pan-start point
nc.graph.point = [0,0];

/**
 * activates when user drags the background
 */
nc.graph.panstarted = function() {   
    var p = d3.mouse(this);  
    // get original translation 
    var oldtrans = nc.graph.svg.select("g.nc-svg-content").attr("transform").split(/\(|,|\)/);
    // record the drag start location
    nc.graph.point = [p[0]-oldtrans[1], p[1]-oldtrans[2]];   
}

/**
 * Performs the panning by adjusting the g.nc-svg-content transformation
 */
nc.graph.panned = function() {
    // compute the content transformation from the current mouse position and the 
    // drag start position
    var thispoint = d3.mouse(this);
    var diffx = thispoint[0]-nc.graph.point[0];
    var diffy = thispoint[1]-nc.graph.point[1];
    nc.graph.svg.select("g.nc-svg-content").
    attr("transform", "translate(" + diffx +","+ diffy +")");    
}

nc.graph.panended = function() {
   
    }


nc.graph.zoom = function() {
    nc.graph.svg.attr("transform", 
        "translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
}


/* ==========================================================================
 * Helper functions
 * ========================================================================== */

/**
 * Make the new link/new node forms look normal
 */
nc.graph.resetForms = function() {
    $('#fg-linkname,#fg-linktitle,#fg-linkclass,#fg-linksource,#fg-linktarget').removeClass('has-warning has-error has-success');
    $('#fg-linkname label').html("Link name:");
    $('#fg-linktitle label').html("Link title:");
    $('#fg-linkclass label').html("Link class:");
    $('#fg-linksource label').html("Source:");
    $('#fg-linktarget label').html("Target:");
    $('#fg-nodename,#fg-nodetitle,#fg-nodeclass').removeClass('has-warning has-error has-success');   
    $('#fg-nodename label').html("Node name");
    $('#fg-nodetitle label').html("Node title");
    $('#fg-nodeclass label').html("Node class");       
}

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


/**
 * Processes the new node form submit action
 */
nc.graph.createNode = function() {
    
    nc.graph.resetForms();        
   
    // check if the user is allowed to create users
    if (!nc.editor) {
        return;
    }
   
    // basic checks on the text boxes    
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
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                $('#fg-nodename').addClass('has-error has-feedback');                
                $('#fg-nodename label').html("Please choose another node name:");                
            } else if (data['success']==true) {                                                                 
                $('#nc-graph-newnode form').attr("disabled", true);    
                $('#nc-graph-newnode button.submit').attr("disabled", true);    
                // replace the node id in from the temporary one to the real one
                var nodeindex = nc.graph.replaceNodeId(oldid, data['data']);
                nc.graph.nodes[nodeindex].name = newname;
                nc.graph.nodes[nodeindex]["class"] = newclass;
                nc.graph.svg.select('circle[id="'+oldid+'"]')
                .attr("id", data['data'])
                .classed('nc-newnode', false).classed('nc-node-highlight', false).
                classed(newclass, true).classed('nc-node-highlight', true); 
                $('#nc-graph-newnode').hide();
            }
        } 
        
        // make user wait a little before next attempt
        setTimeout(function() {
            $('#nc-graph-newnode form').attr("disabled", false);
            $('#nc-graph-newnode button.submit')
            .addClass('btn-success').removeClass("btn-default disabled").html("Create node")
            .attr("disabled", false);
        }, nc.ui.timeout/4);
    }

    );    
}

/**
* * Processes the new link form submit action
*/
nc.graph.createLink = function() {
    
    nc.graph.resetForms();
   
    // check if user has permissions for the action
    if (!nc.editor) {
        return;
    }
   
    // basic checks on the text boxes    
    if (nc.utils.checkFormInput('fg-linkname', "link name", 1) +
        nc.utils.checkFormInput('fg-linksource', "link source", 1) +
        nc.utils.checkFormInput('fg-linktarget', "link target", 1) +        
        nc.utils.checkFormInput('fg-linktitle', "link title", 2) < 4) return 0;    
    
    // fetch from form into variables here
    var oldid = $('#nc-graph-newlink form').attr('val');
    var newname = $('#fg-linkname input').val();    
    var newclass = $('#fg-linkclass button.dropdown-toggle').attr('selection');
   
    // give feedback on the form that a request is being sent
    $('#nc-graph-newlink button.submit')
    .removeClass('btn-success').addClass("btn-default")
    .html("Sending...").attr("disabled", true); 
   
    // send a request to create node
    // post the registration request 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "createNewLink", 
        network_name: nc.network,
        link_name: newname,
        link_title: $('#fg-linktitle input').val(),
        class_name: newclass,
        source_name: $('#fg-linksource input').val(),
        target_name: $('#fg-linktarget input').val()
    }, function(data) {          
        nc.utils.alert(data);                
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                $('#fg-linkname').addClass('has-error has-feedback');                
                $('#fg-linkname label').html("Please choose another link name:");                
            } else if (data['success']==true) {                 
                $('#nc-graph-newlink form').attr("disabled", true);    
                $('#nc-graph-newlink button.submit').attr("disabled", true);    
                // replace the node id in from the temporary one to the real one
                var linkindex = nc.graph.replaceLinkId(oldid, data['data']);
                nc.graph.links[linkindex].name = newname;
                nc.graph.links[linkindex]["class"] = newclass;
                nc.graph.svg.select('line[id="'+oldid+'"]')
                .attr("id", data['data'])
                .classed('nc-newlink', false).classed('nc-link-highlight', false).
                classed(newclass, true).classed('nc-link-highlight', true);    
                $('#nc-graph-newlink').hide();            
            }
        }                 
        // make user wait a little before next attempt
        setTimeout(function() {
            $('#nc-graph-newlink form').attr("disabled", false);
            $('#nc-graph-newlink button.submit')
            .addClass('btn-success').removeClass("btn-default disabled").html("Create link")
            .attr("disabled", false);              
        }, nc.ui.timeout/4);
            
    }
    );
   
    
}



/**
* remove a node from the viz (not from the server)
*/
nc.graph.removeNode = function() {    
    // pause the simulation just in case
    nc.graph.simStop();
    
    // identify the selected node        
    var nodeid = nc.graph.svg.select("circle.nc-node-highlight").attr("id");
         
    // get rid of the node id from the array
    nc.graph.nodes = nc.graph.nodes.filter(function(value, index, array) {
        return (value.id!=nodeid);
    });
    // for links, first reset the source and target to simple labels (not arrays)
    // then remove the links that point to the node
    for (var i=0; i<nc.graph.links.length; i++) {
        nc.graph.links[i].source = nc.graph.links[i].source.id;
        nc.graph.links[i].target = nc.graph.links[i].target.id;
    }    
    nc.graph.links = nc.graph.links.filter(function(value, index, array) {
        return (value.source!=nodeid && value.target!=nodeid); 
    });
        
    // restart the simulation
    nc.graph.initSimulation();
    
    $('#nc-graph-newnode').hide();
}


/**
* remove a highlighted link from the viz
*/
nc.graph.removeLink = function() {    
    // pause the simulation just in case
    nc.graph.simStop();
    
    // identify the selected link        
    var linkid = nc.graph.svg.select("line.nc-link-highlight").attr("id");
             
    // remove the link with that id
    nc.graph.links = nc.graph.links.filter(function(value, index, array) {
        return (value.id!=linkid); 
    });
        
    // restart the simulation
    nc.graph.initSimulation();
    $('#nc-graph-newlink').hide();
}