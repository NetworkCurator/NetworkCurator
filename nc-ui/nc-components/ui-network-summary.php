<?php
/*
 * Page showing network summary
 * Abstract, authors, etc.
 * 
 * Assumes the array $netmeta was already obtained in nc-ui-network
 * 
 */
?>


<h1 class="nc-mt-10"><?php echo $netmeta['network_title']; ?></h1>

<h3 class="nc-mt-10">Abstract</h3>
<div id="network-abstract" class="nc-mt-10"><?php echo $netmeta['network_abstract']; ?></div>
<h4 class="nc-mt-10">Curators</h4>
<?php echo ui_listnames($netmeta['curators']); ?>
<h4 class="nc-mt-10">Authors</h4>
<?php echo ui_listnames($netmeta['authors']); ?>
<h4 class="nc-mt-10">Commentators</h4>
<?php echo ui_listnames($netmeta['commentators']); ?>
<hr/>

<h3>Description</h3>
<div id="network-content" class="nc-mt-10"><?php echo $netmeta['network_content']; ?></div>


<hr/>

