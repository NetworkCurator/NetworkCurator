<?php
/*
 * Page showing summary for one link
 * Description, comments, etc.
 *  
 * Assumes $network is set, also that $_REQUEST['link] contains the link id
 * 
 */

$linksummary = $NCapi->getSummary($network, $_REQUEST['link']);

// transfer the annotation text into $netmd - will be converted to html in browser
foreach (["title", "content", "abstract"] as $i) {
    $netmd[$linksummary[$i]['anno_id']] = $linksummary[$i]['anno_text'];
}

// shorthand for node content anno id
$contid = $linksummary['content']['anno_id'];
$owner = $linksummary['content']['owner_id'];

?>


<div class="row">
    <div class="col-sm-8">
        
        <div class="nc-object-type">Link</div>
        <h1><div id="nc-object-title" class="nc-editable-text nc-md" 
                 val="<?php echo $linksummary['title']['anno_id']; ?>"
                 owner="<?php echo $owner; ?>"></div>
        </h1>

        <h3 class="nc-mt-10 nc-mb-10">Abstract</h3>
        <div id="nc-object-abstract" class="nc-editable-text nc-md"
             val="<?php echo $linksummary['abstract']['anno_id']; ?>"
             owner="<?php echo $owner; ?>"></div>
        <hr/>

        <h3 class="nc-mb-10">Description</h3>
        <div id="nc-object-content" class="nc-editable-text nc-md" 
             val="<?php echo $contid; ?>"
             owner="<?php echo $owner; ?>"></div>
        <hr/>

        <div id="nc-comments" class="media-body" val="<?php echo $contid; ?>"></div>        
        <hr/>
        <div class="nc-mt-10" id="nc-newcomment" uid="<?php echo $uid; ?>" val="<?php echo $contid; ?>"></div>
    
    </div>
</div>
