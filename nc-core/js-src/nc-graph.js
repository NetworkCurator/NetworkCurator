/* 
 * nc-graph.js
 * 
 * Functions that deal with graph display/creation/editing
 * 
 * This file mixes jquery and d3 frameworks 
 * 
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
    svgnodes: [], // access to svg nodes
    svglinks: [], // access to svg links
    svgnodetext: [], // access to svg node names textboxes
    info: {}, // store of downloaded content information about a node/link
    settings: {}, // store settings for the simulation
    sim: {}, // force simulation 
    svg: {}, // the svg div on the page  
    zoombehavior: {}, // d3 zoom behavior
    mode: "select"    
};

// set default values for display settings
nc.graph.settings = {
    // settings for user interface
    tooltip: true,
    wideview: false,
    inactive: false,
    namesize:  12,
    // tuning of simulation
    forcesim: true,
    linklength: 60,
    strength: -80,
    vdecay: 0.5,
    // navigation within a small neighborhood
    local: true,
    searchnodes: [],
    neighborhood: 2
}


/* ====================================================================================
 * Setup at the beginning
 * ==================================================================================== */

/**
 * invoked from the nc-core.js script. Loads graph data and displays the interface
 */
nc.graph.initGraph = function() {
    nc.graph.svg = d3.select('#nc-graph-svg');    
    $('#nc-graph-svg-container').prepend('<span>Loading... (please wait)</span>');    

    // counter to get how many items have been loaded from server at init
    var numloaded = 0;
    
    // load the ontology data
    $.post(nc.api, 
    {
        controller: "NCOntology", 
        action: "getLinkOntology",        
        network: nc.network        
    }, function(data) {         
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            nc.ontology.links = nc.ontology.addLongnames(data['data']); 
            numloaded++;
        }                             
    }
    ); 
    $.post(nc.api, 
    {
        controller: "NCOntology", 
        action: "getNodeOntology",        
        network: nc.network        
    }, function(data) {         
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            nc.ontology.nodes = nc.ontology.addLongnames(data['data']);  
            numloaded++;
        }                             
    }
    );  
     
    // load all graph data 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "getAllNodes",        
        network: nc.network        
    }, function(data) {                 
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            nc.graph.rawnodes = data['data'];
            numloaded++;            
        }                             
    }
    ); 
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: "getAllLinks",        
        network: nc.network        
    }, function(data) {                         
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            nc.graph.rawlinks = data['data']; 
            numloaded++;
        }                             
    }
    );
    
    // function to wait for all ajax data before starting the graph init sequence
    var checkAndInit = function() {                        
        if (numloaded>=4) {            
            $('#nc-graph-svg-container').find('span').remove();
            nc.graph.initInterface();                
            nc.graph.initSimulation();
            nc.graph.simUnpause();            
            return;           
        }                
        // if reached here, not loaded yet
        numloaded+= 0.01;
        setTimeout(checkAndInit, 200);        
    };    
    // start monitoring the load status. When done, initialize the user interface
    checkAndInit();          
    
};

/* ====================================================================================
 * Structure of graph box
 * ==================================================================================== */

/**
 * Build a toolbar, adjust interface, deal with node/link classes
 */
nc.graph.initInterface = function() {
        
    var toolbar = $('#nc-graph-toolbar');
    nc.graph.svg = d3.select('#nc-graph-svg');   

    // create search box
    var svgcontainer = $('#nc-graph-svg-container');
    svgcontainer.parent().prepend(nc.graph.makeSearchBox());

    // create buttons on the svg
    svgcontainer.prepend(nc.graph.makeIconToolbar());

    // set the status codes to integers
    $.each(nc.graph.rawnodes, function(key) { 
        nc.graph.rawnodes[key].status = +nc.graph.rawnodes[key].status;        
    });
    $.each(nc.graph.rawlinks, function(key) { 
        nc.graph.rawlinks[key].status = +nc.graph.rawlinks[key].status;        
    });    
    
    // add elements into nc.ontology.nodes, nc.ontology.links that related to display    
    $.each(nc.ontology.nodes, function(key) {    
        nc.ontology.nodes[key].status = +nc.ontology.nodes[key].status;
        nc.ontology.nodes[key].show = 1;
        nc.ontology.nodes[key].showlabel = 0;
    })
    $.each(nc.ontology.links, function(key) {
        nc.ontology.links[key].status = +nc.ontology.links[key].status;
        nc.ontology.links[key].show = 1;
        nc.ontology.links[key].showlabel = 0;
    })

    // get descriptions of the node and link ontology    
    var pushonto = function(x) {
        var result = [];
        $.each(x, function(key, val) {            
            result.push({
                id: val['class_id'],
                label: val['longname'], 
                val: val['name'],
                status: val['status'],
                show: val['show'],
                showlabel: val['showlabel']
            });
        });        
        return nc.utils.sortByKey(result, 'label');
    }    
    var nodes=pushonto(nc.ontology.nodes), links=pushonto(nc.ontology.links);
    
    // add ontology options to the new node/new link forms   
    $('#nc-graph-newnode #fg-nodeclass .input-group-btn')
    .append(nc.ui.DropdownObjectList("", nodes, "node", false).find('.btn-group'))
    $('#nc-graph-newlink #fg-linkclass .input-group-btn')
    .append(nc.ui.DropdownObjectList("", links, "link", false).find('.btn-group'))
    
    // add buttons to the toolbar
    toolbar.append(nc.ui.ButtonGroup(['Select']));
    toolbar.append(nc.ui.DropdownObjectList("New node ", nodes, "node", false));
    toolbar.append(nc.ui.DropdownObjectList("New link ", links, "link", false));     
    
    toolbar.find("button").click(function() {
        toolbar.find("button").removeClass("active");         
    })    
    
    // make a button that says view, add a dropdown form to it, add to toolbar    
    toolbar.append(nc.graph.makeViewBtn(nodes, links));
    
    // make a button with graph settings    
    toolbar.append(nc.graph.makeSettingsBtn());
    
    // make a button with Save buttons
    toolbar.append(nc.graph.makeSaveBtn());  
  
    // create behaviors in the svg based on the toolbar        
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
            nc.graph.mode = "select";
        }       
    });
    
    // for details box, add buttons for "read more" and to "activate/inactivate"
    var detailsabstract = $('#nc-graph-details-abstract');
    var readbutton = '<a class="btn btn-default btn-sm" href="#" id="nc-graph-details-more">Read more</a>'      
    if (nc.curator) {        
        var togglebutton = '<a class="btn btn-danger btn-sm nc-btn-mh" id="nc-graph-details-remove">Remove</a>';  
        detailsabstract.after(togglebutton);
    }
    detailsabstract.after(readbutton);
            
}


nc.graph.makeIconToolbar = function() {
    
    // create toolbar div
    var obj = $(nc.ui.graphIconToolbar());
                  
    // add handlers for the buttons
    obj.find('.glyphicon-zoom-in').click(function() {
        var svgbg = document.querySelector('.nc-svg-background');
        var nowscale = d3.zoomTransform(svgbg);                 
        nowscale.k = nowscale.k *1.1;        
        nc.graph.svg.select('.nc-svg-background').call(nc.graph.zoombehavior.transform, nowscale);        
    });    
    obj.find('.glyphicon-zoom-out').click(function() {
        var svgbg = document.querySelector('.nc-svg-background');
        var nowscale = d3.zoomTransform(svgbg);                 
        nowscale.k = nowscale.k/1.1;        
        nc.graph.svg.select('.nc-svg-background').call(nc.graph.zoombehavior.transform, nowscale);        
    });    
    // centering of viewpoint
    obj.find('.glyphicon-record').click(nc.graph.centerview);        
    // saving image to disk
    obj.find('.glyphicon-picture').click(nc.graph.saveGraphSVG);        
    // toggling view (wide-narrow view)    
    obj.find('.glyphicon-resize-full').click(function() {     
        nc.graph.toggleWideScreen(true);        
    });    
    obj.find('.glyphicon-resize-small').hide().click(function() {        
        nc.graph.toggleWideScreen(false);        
    });
    obj.find('.glyphicon-play').hide().click(function() {        
        nc.graph.toggleSimulation(true);        
    });
    obj.find('.glyphicon-pause').click(function() {        
        nc.graph.toggleSimulation(false);        
    });
    
    return obj;
}

/**
 * Helper function to initInterface() - creates a button with view node/link options
 * 
 */
nc.graph.makeViewBtn = function(nodes, links) {    
    var viewbtn = nc.ui.DropdownOntoView(nodes, links);    
    // add handlers to the checkboxes in the form
    // attach handling for toggling of the checkboxes
    viewbtn.find('input[type="checkbox"]').on("change", function() {
        // identify whether a show element or show label is clicked
        var showtext = $(this).attr("val")=="showlabel";
        var classid = $(this).parent().parent().attr("val");
        var newval = +this.checked;
        // identify whether classname is a node or link
        var nowonto = (classid in nc.ontology.nodes ? nc.ontology.nodes : nc.ontology.links);
        if (showtext) {
            nowonto[classid].showlabel=newval;  
        } else {
            nowonto[classid].show=newval;  
        }
        // reinitialize the graph simulation
        nc.graph.initSimulation(); 
    });    
    return viewbtn;    
}


/**
 * Helper function to initInterface() - creates a button with settings
 * 
 */
nc.graph.makeSettingsBtn = function() {
        
    var settingsbtn = nc.ui.DropdownGraphSettings();
    
    // fill initial values for the settings text areas
    settingsbtn.find('input').each(function() {
        var nowinput = $(this);
        var nowval = nowinput.attr("val");        
        if (nowinput.attr("type") == "checkbox") {            
            nowinput.prop('checked', nc.graph.settings[nowval]);
        } else {
            // this is text input
            nowinput.val(nc.graph.settings[nowval]);            
        }                                
    });
    
    // create handlers for changing settings
    settingsbtn.find('input').on('change', function() {        
        var nowinput = $(this);                
        var nowsetting = nowinput.attr("val");
        var newval = (nowinput.attr("type")=="checkbox" ? nowinput.is(':checked'): parseFloat(nowinput.val()));        
        nc.graph.settings[nowinput.attr("val")] = newval; 
        if (nowsetting=="wideview") {
            // changing the view size does not require restart of the sim
            nc.graph.toggleWideScreen(newval);            
        } else if (nowsetting=="forcesim") {  
            // toggling the simulation on/off
            nc.graph.toggleSimulation(newval);                        
        } else {        
            // for other, just remake the simulation
            nc.graph.initSimulation();            
        }
    })
  
    return settingsbtn;
}

/**
 * Toggles between wide and narrow view
 */
nc.graph.toggleWideScreen = function(gowide) {    
    var iconset = $('.nc-svgtools');
    if (gowide) {
        // make the graph view wide        
        $('#nc-graph-svg-container').removeClass("col-sm-8").addClass("col-sm-12");  
        $('#nc-graph-side').removeClass("col-sm-4").addClass("col-sm-8");
        iconset.find('.glyphicon-resize-full').hide();        
        iconset.find('.glyphicon-resize-small').show();                        
    } else {
        $('#nc-graph-svg-container').removeClass("col-sm-12").addClass("col-sm-8");
        $('#nc-graph-side').removeClass("col-sm-8").addClass("col-sm-4");
        iconset.find('.glyphicon-resize-full').show();    
        iconset.find('.glyphicon-resize-small').hide();                
    }    
}


/**
 * Toggle simulation on/off
 * 
 * @param onsim - logical, set true to make the simulation go on, false to pause
 */
nc.graph.toggleSimulation = function(onsim) {
    nc.graph.settings.forcesim = onsim;
    var iconset = $('.nc-svgtools');
    // fixing the layout does not require restart of the sim
    if (onsim) {                                
        for (var i=0; i<nc.graph.rawnodes.length; i++) {
            nownode = nc.graph.rawnodes[i];
            nownode.fx=null;
            nownode.fy=null;
        }
        nc.graph.simUnpause();
        iconset.find('.glyphicon-pause').show();
        iconset.find('.glyphicon-play').hide();
    } else {                
        for (var i=0; i<nc.graph.rawnodes.length; i++) {
            var nownode = nc.graph.rawnodes[i];
            nownode.fx=nownode.x;
            nownode.fy=nownode.y;
        }
        iconset.find('.glyphicon-pause').hide();
        iconset.find('.glyphicon-play').show();
    }
}


/**
 * Helper function to initInterface() - creates a button with save options
 * 
 */
nc.graph.makeSaveBtn = function() {
    
    var savebtn = nc.ui.DropdownGraphSave();
    
    // attach actions to the save lins
    savebtn.find('a[val="diagram"]').click(nc.graph.saveGraphSVG);
            
    // save the current visible nodes
    savebtn.find('a[val="nodes"]').click(function() {        
        // generate a nodelist (current view only)
        var nodelist = [];
        nodelist.push("Node.name\tNode.class");
        for (var i=0; i<nc.graph.nodes.length; i++) {
            var nownode = nc.graph.nodes[i];
            nodelist.push(nownode["name"]+"\t"+nownode["class"]);
        }
        nodelist = nodelist.join("\n");                
        // generate a filename and save
        var filename = nc.network+"_"+nc.userid+"_nodes.txt";                
        nc.utils.saveToFile(nodelist, filename);                
    });
            
    return savebtn;
}


// captures current graph state and triggers an svg file download
nc.graph.saveGraphSVG = function() {
    // find dimensions of the svg
    var width = parseInt(nc.graph.svg.style("width"));    
    var height = parseInt(nc.graph.svg.style("height"));
    // create text with svg content
    var svgdef = '<svg width="'+width+'" height="'+height+'">';
    // create copy of the svg (will simplify before saving)
    var nowsvg = $('#nc-graph-svg').clone();
    // helper function to report a number to 5 sig fig
    var sf5 = function(x) {
        return Number(x).toPrecision(5);
    }   
    // remove tooltip
    nowsvg.find('#nc-svg-tooltip').remove();
    // remove spurious styles
    nowsvg.find('use,rect,line,text')
    .css('-webkit-tap-highlight-color', '')
    .css('pointer-events', '');
    // round the x, y coordinates of nodes and text elements
    nowsvg.find('use,text').each(function() {
        var nn = $(this);
        nn.attr("x", sf5(nn.attr("x")));
        nn.attr("y", sf5(nn.attr("y")));
    });
    // round the x1, y1, x2, y2 coordinates of links
    nowsvg.find('line').each(function() {
        var nn = $(this);
        nn.attr("x1", sf5(nn.attr("x1")));
        nn.attr("y1", sf5(nn.attr("y1")));
        nn.attr("x2", sf5(nn.attr("x2")));
        nn.attr("y2", sf5(nn.attr("y2")));
    });

    svgdef += nowsvg.html()+'</svg>';        
    // save to file    
    var filename = nc.network+"_network.svg";                
    nc.utils.saveToFile(svgdef, filename);
}


/* ====================================================================================
 * Handlers for graph search box
 * ==================================================================================== */

/**
 * Scans the search box and returns an array of search items
 */
nc.graph.getSearchElements = function() {
    var result = [];
    $('#nc-graph-search span.nc-search-item').each(function() {
        result.push($( this ).text());
    });    
    return result;
}


/**
 * queries whether the search div has an element with name x
 */
nc.graph.hasSearchElement = function(x) {
    var nowitems = nc.graph.getSearchElements();
    return (nowitems.indexOf(x)>=0);
}


/**
 * removes one element from the search box
 */
nc.graph.removeSearchElement = function(x) {
    x = x.trim();
    $('#nc-graph-search span.nc-search-item').each(function() {
        if ($( this ).text()==x) {
            $(this).remove();
        }
    });
    nc.graph.simStart();
}

/**
 * Add one node to the search set
 * 
 * @param x - text to add to the text box
 * @param restart - logical, determines if the simulation should be restarted
 * @return true if a term was actually added
 */
nc.graph.addSearchElement = function(x, restart) {
                    
    if (!nc.graph.hasSearchElement(x)) {       
        var newspan = nc.ui.searchSpan(x, nc.graph.rawnodes);
        $('#nc-graph-search #nc-input').before(newspan);                
        if (restart) {
            nc.graph.simStart();
        }
        if (!newspan.hasClass('nc-search-unknown')) {        
            return true;        
        }
    } 
    
    return false;    
}


/**
 * compares a user input to the node names, 
 * shows auto-complete suggestions in a dropdown
 */
nc.graph.populateSearchSuggestions = function(x) {
    var searchlist = $('#nc-graph-search ul');     
    if (x.length<2) {
        searchlist.hide();
        return;
    }
        
    // helper function creates new <li> object 
    var newli = function(y) {
        return $('<li><a>'+y+'</a></li>');
    } 
    
    // scan nodes and add to searchlist
    searchlist.html('').show().css("left", $('#nc-input').position().left+"px" );    
    var xre = new RegExp(x);
    var rlen = nc.graph.rawnodes.length;
    for (var i=0; i<rlen; i++) {
        var iname = nc.graph.rawnodes[i].name;
        if (xre.test(iname)) {            
            searchlist.append(newli(iname));
        }
    }
    
    // if searchlist empty, write out a mesage
    if (searchlist.find('li').length==0) {        
        searchlist.append(newli('-- no hits --'));
    }
    
}

/**
 * Create an object with a search box and handlers.
 */
nc.graph.makeSearchBox = function() {
    
    var searchbox = nc.ui.graphSearchBox();

    // helper function to reset the search box
    var searchreset = function(restart) {        
        searchbox.find('ul').hide();
        if (restart) {
            nc.graph.simStart();
        }
        searchbox.find('input').val("").focus();
    }

    // attach a handler that will add search items into the div
    searchbox.find('input').on("keyup", function(e) {  
        var nowval = $(this).val().trim();            
        // spacebar or enter in the input box -> new search item
        // other characters -> adjust suggestion box
        if (e.keyCode==32 || e.keyCode==13) {    
            // respond to spacebar            
            // split it up into smaller bits (handles cut/paste)            
            nowval = nowval.split(/ |\n/); 
            var result = 0;
            for (var i=0; i<nowval.length; i++) {                                
                result += nc.graph.addSearchElement(nowval[i].trim(), false);                        
            }          
            searchreset(result>0); 
        } else if (e.keyCode==40) {
        // processing down key? (could get it to work)            
        } else {
            // respond to other characters
            nc.graph.populateSearchSuggestions(nowval);
        }        
    });
    
    // attach a handler to remove items
    searchbox.on("click", 'span.glyphicon-remove', 
        function() {                        
            nc.graph.removeSearchElement($(this).parent().text());        
        });   
        
    // handler to respond to user choosing search nodes from dropdown list
    searchbox.on("click", 'li',
        function() {
            var nowval = $(this).find('a').text();  
            if (nc.utils.checkString(nowval, 1)) {
                nc.graph.addSearchElement(nowval, false);
                searchreset(true);            
            }
        })
        
    return searchbox;
}
    


/* ====================================================================================
 * Handlers for graph editing
 * ==================================================================================== */

// invoked by mouse click
nc.graph.addNode = function() {
    // find the current node type, then add a classed node 
    var whichnode = d3.select('#nc-graph-toolbar button[val="node"]');
    var classid = whichnode.attr('class_id');
    var classname = whichnode.attr('class_name');
    var newnode = nc.graph.addClassedNode(nc.graph.getCoord(d3.mouse(this)), classid, classname, "nc-newnode");            
    nc.graph.selectObject(newnode);
    return newnode;
}

// helper function, invoked from addNode()
nc.graph.addClassedNode = function(point, classid, classname, styleclass) {     
    var newid = "+"+classname+"_"+Date.now(),    
    newnode = {
        "id": newid,
        "name": newid,
        "class_id": classid,
        "class_name": classname,
        "class": styleclass,
        "status": 1,
        x: point[0], 
        y: point[1],
        fx: point[0], 
        fy: point[1]
    };    
    //alert(JSON.stringify(newnode));
    nc.graph.simStop();
    nc.graph.rawnodes.push(newnode);          
    nc.graph.initSimulation(); 
    
    return newnode;
}

// invoked by mouse events
nc.graph.addLink = function() {
    var whichlink = d3.select('#nc-graph-toolbar button[val="link"]');    
    var classid = whichlink.attr("class_id");
    var classname =  whichlink.attr("class_name");
    var newlink = nc.graph.addClassedLink(classid, classname, "nc-newlink");
    nc.graph.selectObject(newlink);    
    return newlink;
}

// helper function, invoked from addLink()
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
        "status": 1,
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
nc.graph.selectObject = function(d) {
          
    if (d==null) {
        return;
    }

    // un-highlight existing, then highlight required id
    nc.graph.unselect();    
    if ("source" in d) {
        nc.graph.svg.select('line[id="'+d.id+'"]').classed('nc-link-highlight', true);
    } else {
        nc.graph.svg.select('use[id="'+d.id+'"]').classed('nc-node-highlight', true);       
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
        nc.graph.displayInfo(d);
    }
                    
}

/**
 * Remove all highlight styling from components in the graph
 */
nc.graph.unselect = function(d) {
    nc.graph.svg.selectAll('use').classed('nc-node-highlight',false);
    nc.graph.svg.selectAll('line').classed('nc-link-highlight',false);  
    $('#nc-graph-newnode,#nc-graph-newlink,#nc-graph-details').hide();    
}


/**
 * @param d
 * 
 */
nc.graph.displayInfo = function(d) {     
    
    if (d.id==null) return;
         
    var prefix = "nc-graph-details";
    var ncc = "nc-curation-content";
    
    // get the details div and clear its content
    var detdiv = $('#'+prefix);
    detdiv.find('#'+prefix+'-class').html("Loading...");        
    detdiv.find('.nc-md .'+ncc).html("Loading...");    
    detdiv.find('#'+prefix+'-more').attr("href", "?network="+nc.network+"&object="+d.id);
    if (nc.curator) {
        var type = ("source" in d ? "Link" : "Node");  
        var removeactivate = (d.status<1 ? "Activate": "Remove");                
        detdiv.find('#'+prefix+'-remove').html(removeactivate).unbind("click").click(
            function() {                
                nc.graph.toggleObject(d.id, d.name, type, d.status);                                
            });    
    }
    
    detdiv.show();
    
    // perhaps fetch the data from server, or look it up in memory
    if (!(d.id in nc.graph.info)) {
        nc.graph.getInfo(d);
        return;
    }
        
    // if reached here, the graph.info has data on this object       
    // transfer the data into the page
    var dtitle = nc.graph.info[d.id]['title'];
    var dabstract = nc.graph.info[d.id]['abstract'];     
        
    var dettitle = detdiv.find('#'+prefix+'-title');
    dettitle.attr("val", dtitle['anno_id']);
    dettitle.find('div.'+ncc).html(nc.utils.md2html(dtitle['anno_text']));
    dettitle.find('textarea.'+ncc).html(dtitle['anno_text']);    
        
    var detabs = detdiv.find('#'+prefix+'-abstract');
    detabs.attr("val", dabstract['anno_id']);
    detabs.find('div.'+ncc).html(nc.utils.md2html(dabstract['anno_text']));
    detabs.find('textarea.'+ncc).html(dabstract['anno_text']);            
    
    // also fill in the ontology class 
    detdiv.find('#'+prefix+'-class').html("Class: "+d['class']);    
    
}



/**
 * Fetch summary data associated with an object id
 * 
 * This function does not do any user-interface modifications.
 * It just fetches the data and stores it into the info object.
 * Upon completion, it calls nd.graph.selectObject(d) to again select the object and 
 * actually display the information.
 * 
 */
nc.graph.getInfo = function(d) {    
    // send a request to server
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "getSummary", 
        network: nc.network,
        root_id: d.id,
        name: 1,
        title: 1,
        'abstract': 1,
        content: 0
    }, function(data) {                      
        //alert(data);
        nc.utils.alert(data);        
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {
            // push the obtained data into the info object (avoids re-fetch later)
            nc.graph.info[d.id] = data['data'];                             
        } else {
            nc.graph.info[d.id] = {
                "title": "NA", 
                "abstract": "NA"
            };            
        }                              
        nc.graph.selectObject(d);
    });
}

/* ====================================================================================
 * Node/Link activate/remove
 * ==================================================================================== */

/**
 * Invoked by user clicking to remove/activate a node or link
 */
nc.graph.toggleObject = function(objid, objname, objtype, objstatus) {    
    
    var action = (objstatus<1 ? "activate": "remove");    
        
    $.post(nc.api, 
    {
        controller: "NCGraphs", 
        action: action+objtype,
        network: nc.network,
        name: objname        
    }, function(data) {         
        data = JSON.parse(data);
        if (nc.utils.checkAPIresult(data)) {            
            if (data['success']==false || data['data']==false) {
                nc.msg('Error', data['errormsg']);
            } else if (data['success']==true) {                      
                // success, so adjust the svg, adjust the rawnodes
                if (objtype=="Link") {
                    nc.graph.svg.select('line[id="'+objid+'"]').classed('nc-inactive', objstatus==1);                     
                    for (var i=0; i<nc.graph.rawlinks.length; i++) {
                        if (nc.graph.rawlinks[i].id==objid) {
                            nc.graph.rawlinks[i].status = +(objstatus<1);
                        }
                    }
                } else {
                    nc.graph.svg.select('use[id="'+objid+'"]').classed('nc-inactive', objstatus==1);                     
                    for (var i=0; i<nc.graph.rawnodes.length; i++) {
                        if (nc.graph.rawnodes[i].id==objid) {                            
                            nc.graph.rawnodes[i].status = +(objstatus<1);
                        }
                    }
                }                
            } // end of if data[] handling
        }        
    })

}


/* ====================================================================================
 * Node/Link filtering
 * ==================================================================================== */

/**
 * Turns a large set of raw nodes into a smaller set for display.
 * This enables suppressing some graph info while displaying others.
 */
nc.graph.filterNodes = function() {         
        
    // convenience shallow copies of nc.graph objects
    var rawnodes = nc.graph.rawnodes;
    var nonto = nc.ontology.nodes;    
    var nlen = rawnodes.length;    
    // loop and copy certain nodes from raw to new
    var counter = 0;
    nc.graph.nodes = [];
    for (var i=0; i<nlen; i++) {
        // start with default status for node (yes, if it is a new node, false otherwise)
        var iok = rawnodes[i]['class']=="nc-newnode";                
        // get input class id        
        var iclassid = rawnodes[i].class_id;         
        // check if the ontology allows this class to display
        if (nonto[iclassid].show>0) {
            // check if the status of the node and ontology is visible
            if (nc.graph.settings.inactive==1 || 
                (rawnodes[i].status>0 && nonto[iclassid].status>0)) {                
                iok = true;
            }    
        } 
        // if node passes criteria, filter positive
        if (iok) {            
            nc.graph.nodes[counter] = rawnodes[i];
            counter++;
        }
    }
    
}

/**
 * Turns a large set of raw links into a smaller set for display.
 * Analogous to filterNodes.
 * 
 */
nc.graph.filterLinks = function() {    
    
    // some shorthand objects
    var rawlinks = nc.graph.rawlinks;
    var llen = rawlinks.length;
    var lonto = nc.ontology.links;

    // get an array of all available nodes
    var goodnodes = {};
    for (var j=0; j<nc.graph.nodes.length; j++) {
        goodnodes[nc.graph.nodes[j].id]= 1;
    }    
    
    var counter = 0;
    nc.graph.links = [];        
    for (var i=0; i<llen; i++) {
        var iclassid = rawlinks[i].class_id;  
        // always display new links
        var iok = rawlinks[i]['class']=="nc-newlink";
        // check the ontology should be visible
        if (lonto[iclassid].show>0) {
            // cehck that the links are active/inactive
            if (nc.graph.settings.inactive==1 || 
                (rawlinks[i].status>0 && lonto[iclassid].status>0)) {
                var isource = rawlinks[i].source;
                var itarget = rawlinks[i].target;
                // check that the source/target nodes are visible
                if ((isource in goodnodes || isource.id in goodnodes) && 
                    (itarget in goodnodes || itarget.id in goodnodes)) {                                
                    iok=true;
                }                                 
            }
        }       
        if (iok) {
            nc.graph.links[counter] = nc.graph.rawlinks[i];
            counter++;
        }
    }

}

/**
 * Starts with nc.graph.nodes and nc.graph.links and further trims them to 
 * leave only those nodes/links at a certain graph distance from a center node
 * 
 */
nc.graph.filterNeighborhood = function() {
    
    nc.graph.settings.searchnodes = nc.graph.getSearchElements();
    // if no local neighborhood is required, do nothing
    if (!nc.graph.settings.local || nc.graph.settings.searchnodes.length==0) {
        return;
    }
    
    // start with nc.graph.nodes and nc.graph.links that are part of the search query
    var oknodes = {};
    for (var i=0; i<nc.graph.settings.searchnodes.length; i++) {
        oknodes[nc.graph.settings.searchnodes[i]] = true;
    }
        
    // also include the nodes that are nc-newnode
    for (var i=0; i<nc.graph.rawnodes.length; i++) {
        if (nc.graph.rawnodes[i]['class']=="nc-newnode") {
            oknodes[nc.graph.rawnodes[i]['name']] = true;
        }
    }
    
    // loop over the links 'd' times 
    var nowd = 0;
    while (nowd < nc.graph.settings.neighborhood) {
        // get a subset of links that touch on the selected nodes
        var oklinks = nc.graph.links.filter(function(v) {
            if (v['class']=="nc-newlink") return true;
            return v.source.name in oknodes || v.target.name in oknodes;
        });        
        // transfer the node names from oklinks to oknodes        
        for (var i=0; i<oklinks.length; i++) {            
            oknodes[oklinks[i].source.name] = true;
            oknodes[oklinks[i].target.name] = true;
        }
        nowd++;        
    }        
    // filter the graph nodes and graph links to touch the neighborhood nodes only
    nc.graph.nodes = nc.graph.nodes.filter(function(v) {
        return v.name in oknodes;
    });        
    nc.graph.links = nc.graph.links.filter(function(v) {
        if (v['class']=="nc-newlink") return true;
        return (v.source.name in oknodes && v.target.name in oknodes);
    });     
}


/* ====================================================================================
 * Interactions (dragging, panning, zooming)
 * ==================================================================================== */

/**
 * Called when user double-clicks on a node/text linked with a node
 * Adds/removes the node from the search box
 */
nc.graph.toggleSearchNode = function(d) {        
    // early exit if nothing needs to be changed
    if (nc.graph.hasSearchElement(d.name)) {
        nc.graph.removeSearchElement(d.name);
    } else {
        nc.graph.addSearchElement(d.name, true);
    }    
}

/**
 * Unpin the position of a node
 */
nc.graph.pinToggle = function(d) {    
    if ("index" in d) {
        var dobj = nc.graph.nodes[d.index];
        if (dobj.fx === null) {
            dobj.fx = dobj.x;
            dobj.fy = dobj.y;                   
        } else {
            dobj.fx = null;
            dobj.fy = null;                   
        }
    }
}


/**
 * @param d the object (node) that is being dragged
 */
nc.graph.dragstarted = function(d) {  
    
    // pass on the event to "select" - this will highlights the dragged object
    nc.graph.selectObject(d);
    
    switch (nc.graph.mode) {
        case "select":
            if (!d3.event.active) nc.graph.sim.alphaTarget(0.3).restart();               
            break;
        case "newlink":
            nc.graph.simStop(); 
            nc.graph.svg.select('use[id="'+d.id+'"]').classed("nc-newlink-source", true);
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
                nc.graph.svg.selectAll('use').classed('nc-newlink-target', false);
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
            // for active simulations, reset the fx, for inactive, keep the coordinates fixed
            if (nc.graph.settings.forcesim) {
                nc.graph.nodes[dindex].fx = null;
                nc.graph.nodes[dindex].fy = null;
            } 
            break;
        case "newlink":
            // this is here to make links respond to drag events                        
            var newlink = nc.graph.addLink(); 
            nc.graph.selectObject(newlink);            
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
    nc.graph.point = [p[0]-oldtrans[1], p[1]-oldtrans[2], oldtrans[4]];   
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
    nc.graph.svg.select("g.nc-svg-content")
    .attr("transform", "translate(" + diffx +","+ diffy +")scale("+nc.graph.point[2]+")");    
}


/**
 * Perform rescaling on the content svg upon zoomin
 */
nc.graph.zoom = function() {
    // get existing transformation
    var oldtrans = nc.graph.getTransformation();
    var oldscale = oldtrans[2];
    // apply a scaling transformation manually    
    var newscale = d3.event.transform.k;       
    var sw2 = parseInt(nc.graph.svg.style("width").replace("px",""))/2;
    var sh2 = parseInt(nc.graph.svg.style("height").replace("px",""))/2; 
    var newtrans = [sw2 + (oldtrans[0]-sw2)*(newscale/oldscale), 
    sh2+(oldtrans[1]-sh2)*(newscale/oldscale), newscale];    
    // set the new transformation into the content
    nc.graph.svg.select("g.nc-svg-content")
    .attr("transform", "translate(" + newtrans[0] +","+ newtrans[1] +")scale("+newtrans[2]+")")            
}


/**
 * perform a translate operation so that nodes appear in the center of the graph view
 */
nc.graph.centerview = function() {    
    // collect information about current viewpoint
    var oldtrans = nc.graph.getTransformation();
    var sw2 = parseInt(nc.graph.svg.style("width").replace("px",""))/2;
    var sh2 = parseInt(nc.graph.svg.style("height").replace("px",""))/2;         
        
    // set current transformation to center viewpoint in the middle
    var newtrans = [sw2*(1-oldtrans[2]), sh2*(1-oldtrans[2])];    
    nc.graph.svg.select("g.nc-svg-content")
    .attr("transform", "translate("+newtrans[0]+","+newtrans[1]+")scale("+oldtrans[2]+")")            
}


/* ====================================================================================
 * Helper functions
 * ==================================================================================== */

/**
 * Converts between a mouse coordinate p to an avg coordinate 
 * (The differences comes from transformations and scaling)
 */
nc.graph.getCoord = function(p) {        
    var trans = nc.graph.getTransformation();
    return [(p[0]-trans[0])/trans[2], (p[1]-trans[1])/trans[2]];
}

/**
 * Make the new link/new node forms look normal
 */
nc.graph.resetForms = function() {
    //$('#fg-linkname,#fg-linktitle,#fg-linkclass,#fg-linksource,#fg-linktarget').removeClass('has-warning has-error has-success');
    $('#fg-linkname,#fg-linkclass,#fg-linksource,#fg-linktarget').removeClass('has-warning has-error has-success');
    $('#fg-linkname label').html("Link name:");
    //$('#fg-linktitle label').html("Link title:");
    $('#fg-linkclass label').html("Link class:");
    $('#fg-linksource label').html("Source:");
    $('#fg-linktarget label').html("Target:");
    //$('#fg-nodename,#fg-nodetitle,#fg-nodeclass').removeClass('has-warning has-error has-success');   
    $('#fg-nodename,#fg-nodeclass').removeClass('has-warning has-error has-success');   
    $('#fg-nodename label').html("Node name");
    //$('#fg-nodetitle label').html("Node title");
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


/* ====================================================================================
 * Communicating with server
 * ==================================================================================== */

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
    if (nc.utils.checkFormInput('fg-nodename', "node name", 1)<1) return 0;
    //nc.utils.checkFormInput('fg-nodetitle', "node title", 2) < 2) return 0;    
   
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
        network: nc.network,
        name: newname,        
        'title': newname,
        'abstract': newname+" [abstract]",
        'content': newname +" [content]",
        'class': newclass
    }, function(data) {          
        nc.utils.alert(data);                
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
                nc.graph.svg.select('use[id="'+oldid+'"]')
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
        nc.utils.checkFormInput('fg-linktarget', "link target", 1) <3) return 0;        
    //nc.utils.checkFormInput('fg-linktitle', "link title", 2) < 4) return 0;    
    
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
        network: nc.network,
        name: newname,
        //title: $('#fg-linktitle input').val(),
        'title': newname,
        'abstract': newname +" [abstract]",
        'content': newname +" [content]",
        'class': newclass,
        source: $('#fg-linksource input').val(),
        target: $('#fg-linktarget input').val()
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
    var nodeid = nc.graph.svg.select("use.nc-node-highlight").attr("id");
         
    // get rid of the node id from the array    
    nc.graph.rawnodes = nc.graph.rawnodes.filter(function(value) {
        return (value.id!=nodeid);
    });    
    // for links, first reset the source and target to simple labels (not arrays)
    // then remove the links that point to the node
    for (var i=0; i<nc.graph.rawlinks.length; i++) {
        nc.graph.rawlinks[i].source = nc.graph.rawlinks[i].source.id;
        nc.graph.rawlinks[i].target = nc.graph.rawlinks[i].target.id;
    }    
    nc.graph.rawlinks = nc.graph.rawlinks.filter(function(value) {
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
    nc.graph.rawlinks = nc.graph.rawlinks.filter(function(value, index, array) {
        return (value.id!=linkid); 
    });
        
    // restart the simulation
    nc.graph.initSimulation();
    $('#nc-graph-newlink').hide();
}

