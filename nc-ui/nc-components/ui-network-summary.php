<?php
/*
 * Page showing network summary
 * Abstract, authors, etc.
 * 
 * Assumes the array $netmeta was already obtained in nc-ui-network
 * 
 */

// fetch metadata, this time with extended content
$netmeta = $NCapi->getNetworkMetadata($network);

// owner of the annotation
$owner = 'admin';

// transfer the annotation text into $netmd - will be converted to html in browser
foreach (["title", "content", "abstract"] as $i) {
    $netmd[$netmeta[$i]['anno_id']] = $netmeta[$i]['anno_text'];
}

?>

<div class="row">
    <div class="col-sm-8">
        <h1><div id="nc-network-title" class="nc-editable-text nc-md" 
                 val="<?php echo $netmeta['title']['anno_id']; ?>"
                 owner="<?php echo $owner; ?>"></div>
        </h1>

        <h4 class="nc-mt-10">Curators</h4>
        <?php echo ncListnames($netmeta['curators']); ?>
        <h4 class="nc-mt-10">Editors</h4>
        <?php echo ncListnames($netmeta['authors']); ?>
        <h4 class="nc-mt-10">Commentators</h4>
        <?php echo ncListNames($netmeta['commentators']); ?>
        <hr/>
        
        <h3 class="nc-mt-10 nc-mb-05">Abstract</h3>
        <div id="nc-network-abstract" class="nc-editable-text nc-md"
             val="<?php echo $netmeta['abstract']['anno_id']; ?>"
             owner="<?php echo $owner; ?>">
        </div>        
        <hr/>

        <h3 class="nc-mb-10">Description</h3>
        <div id="nc-network-content" class="nc-editable-text nc-md" 
             val="<?php echo $netmeta['content']['anno_id']; ?>"
             owner="<?php echo $owner; ?>">        
        </div>
        <hr/>

        <div id="nc-comments" class="media-body" val="<?php echo $netmeta['content']['anno_id']; ?>"></div>        
        <hr/>
        <div class="nc-mt-10" id="nc-newcomment" uid="<?php echo $uid; ?>" val="<?php echo $netmeta['content']['anno_id']; ?>"></div>

    </div>
</div>
