<?php
/*
 * Page showing summary for one node
 * Description, comments, etc.
 *  
 * Assumes $network is set, also that $_REQUEST['link] contains the link id
 * 
 */

// fetch summary
$nodesummary = $NCapi->getSummary($network, $_REQUEST['node']);

// transfer the annotation text into $netmd - will be converted to html in browser
foreach (["title", "content", "abstract"] as $i) {
    $netmd[$nodesummary[$i]['anno_id']] = $nodesummary[$i]['anno_text'];
}

// shorthand for node content anno id
$contid = $nodesummary['content']['anno_id'];
$owner = $nodesummary['content']['owner_id'];

?>

<div class="row">
    <div class="col-sm-8">
        
        <div class="nc-object-type"><span>Node: </span><?php echo $nodesummary['name']['anno_text']; ?></div>
        <h1><div id="nc-object-title" class="nc-editable-text nc-md" 
                 val="<?php echo $nodesummary['title']['anno_id']; ?>"
                 owner="<?php echo $owner; ?>"></div>
        </h1>
        
        <h3 class="nc-mt-10 nc-mb-10">Abstract</h3>
        <div id="nc-object-abstract" class="nc-editable-text nc-md"
             val="<?php echo $nodesummary['abstract']['anno_id']; ?>"
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


