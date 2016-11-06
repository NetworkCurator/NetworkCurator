<?php

/*
 * Page showing summary for one object (node, link, class)
 * Description, comments, etc.
 *  
 * Assumes $network is set, also that $_REQUEST['object'] contains the object id
 * 
 */

// identify object type
$objid = $_REQUEST['object'];

if (substr($objid, 0,1)==NC_PREFIX_CLASS) {
    $objtype = "Ontology class";
} else if (substr($objid, 0,1)==NC_PREFIX_NODE) {
    $objtype = "Node";
} else if (substr($objid, 0,1)==NC_PREFIX_LINK) {
    $objtype = "Link";
}

// fetch summary
$summary = $NCapi->getSummary($network, $_REQUEST['object']);
//print_r($summary);

// transfer the annotation text into $netmd - will be converted to html in browser
foreach (["title", "content", "abstract"] as $i) {
    $netmd[$summary[$i]['anno_id']] = $summary[$i]['anno_text'];
}

// shorthand for node content anno id
$contid = $summary['content']['anno_id'];
$owner = $summary['content']['owner_id'];

?>

<div class="row">
    <div class="col-sm-8">
        
        <div class="nc-object-type"><span><?php echo "Ontology: "; ?> </span><?php echo $summary['name']['anno_text']; ?></div>
        <div class="nc-object-type"><span>Created by: </span><?php echo $owner; ?></div>
        
        <h1><div id="nc-object-title" class="nc-editable-text nc-md" 
                 val="<?php echo $summary['title']['anno_id']; ?>"
                 owner="<?php echo $owner; ?>"></div>
        </h1>
        
        <h3 class="nc-mt-10 nc-mb-10">Abstract</h3>
        <div id="nc-object-abstract" class="nc-editable-text nc-md"
             val="<?php echo $summary['abstract']['anno_id']; ?>"
             owner="<?php echo $owner; ?>"></div>
        <hr/>

        <h3 class="nc-mb-10">Description</h3>
        <div id="nc-object-content" class="nc-editable-text nc-md" 
             val="<?php echo $contid; ?>"
             owner="<?php echo $owner; ?>"></div>
        <hr/>

        <div id="nc-comments" class="media-body" val="<?php echo $contid; ?>"></div>        
        <hr/>
        <div class="nc-mt-10" id="nc-newcomment" uid="<?php echo $uid; ?>" 
             val="<?php echo $contid; ?>"></div>
    
    </div>
</div>


