/* 
 * nc-history.js
 * 
 * Functions that deal with annotation history
 * 
 * 
 */

/* global nc */


if (typeof nc === "undefined") {
    throw new Error("nc is undefined");
}
nc.history = {
    annoid: "", // the id of the annotation
    data: []    // will hold data from server
};
    

/* ====================================================================================
 * Setup at the beginning
 * ==================================================================================== */

/**
 * invoked from the nc-core.js script. Loads graph data and displays the interface
 */
nc.history.initHistory = function() {
    
    // abort if annoid is not set
    if (nc.history.annoid==="") {
        return;        
    }
    
    // load the annotation history
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "getHistory",        
        network: nc.network,
        anno_id: nc.history.annoid
    }, function(data) {                
        data = JSON.parse(data);        
        if (nc.utils.checkAPIresult(data)) {            
            nc.history.makeHistory(data['data']);
        }                             
    }
    );              
};


/**
 * invoked after history data dd is obtained from server
 * 
 * @param dd object with a summary of historical changes to an annotation object
 * 
 */
nc.history.makeHistory = function(dd) {

    // fetch objects for timeline and text preview
    var hline = $('#nc-history-timeline');
    var htext = $('#nc-history-text');
    
    // create entries into the timeline box
    for (var i=0; i<dd.length; i++) {
        hline.append(nc.ui.timelineEntry(dd[i], i));
    }
        
    // attach handlers to clicks
    $('.nc-timeline-entry').on("click", function() {        
        var timelinei = $(this).attr("val");
        // set active states
        $('.nc-timeline-entry').removeClass('nc-timeline-active');
        $(this).addClass('nc-timeline-active');        
        // set text in the htext box
        htext.html(nc.utils.md2html(dd[timelinei]["anno_text"]));        
    });
    
    // trigger a click on the first element to preview the most recent version
    hline.find('.nc-timeline-entry[val="0"]').click();    
};






