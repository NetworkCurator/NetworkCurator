<?php
/*
 * Page showing network summary
 * Abstract, authors, etc.
 * 
 * Assumes the array $netmeta was already obtained in nc-ui-network
 * 
 */
?>


<h1 class="nc-mt-10"><?php echo $netmeta['title']; ?></h1>

<div id="network-abstract" class="nc-mt-10"><?php echo $netmeta['description']; ?></div>
<h3 class="nc-mt-10">Curators</h3>
<?php echo ui_listnames($netmeta['curators']); ?>
<h3 class="nc-mt-10">Authors</h3>
<?php echo ui_listnames($netmeta['authors']); ?>
<h3 class="nc-mt-10">Commentators</h3>
<?php echo ui_listnames($netmeta['commentators']); ?>

