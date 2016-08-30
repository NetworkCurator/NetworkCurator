<?php
/*
 * Page showing network activity log
 * 
 */


// find out total number of records in the log
$logsize = $NCapi->getNetworkActivityLogSize($network);

?>


<div id="nc-activity-log-toolbar"></div>
<div id="nc-activity-log" class="nc-mt-10"></div>

<script>    
    $(document).ready(
    function () {            
        ncBuildActivityLogToolbar("<?php echo $network; ?>", <?php echo $logsize; ?> );        
    });
</script>


