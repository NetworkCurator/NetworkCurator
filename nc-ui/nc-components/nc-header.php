<?php
/**
 * Header at the top of each page
 */
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/jquery-3.1.0/jquery-3.1.0.min.js'></script>                                                                          
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/jquery-sortable-0.9.13/jquery-sortable-min.js'></script>
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/bootstrap-3.3.7/js/bootstrap.min.js'></script>
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/showdown-1.4.2/dist/showdown.min.js'></script>
        <script type='text/javascript'>nc_api='<?php echo NC_CORE_PATH; ?>/networkcurator.php'</script>
        <script type='text/javascript' src='<?php echo NC_JS_PATH; ?>/networkcurator-ui.js'></script>        
        <script type='text/javascript' src='<?php echo NC_JS_PATH; ?>/networkcurator-graph.js'></script>        
        <script type='text/javascript' src='<?php echo NC_JS_PATH; ?>/networkcurator.js'></script>        
        <link rel='stylesheet' href='<?php echo NC_INCLUDES_PATH; ?>/bootstrap-3.3.7/css/bootstrap.min.css'>
        <link rel='stylesheet' href='<?php echo NC_INCLUDES_PATH; ?>/font-awesome-4.6.3/css/font-awesome.min.css'>
        <link rel='stylesheet' href='<?php echo NC_CSS_PATH; ?>/networkcurator.css'>
        <link rel='stylesheet' media='screen and (max-width: 400px)' href='<?php echo NC_CSS_PATH; ?>/networkcurator-small.css' />      
        <!-- <link href="https://fonts.googleapis.com/css?family=Montserrat:700|Roboto" rel="stylesheet">  -->
        <link href="https://fonts.googleapis.com/css?family=Open+Sans|Roboto:700" rel="stylesheet">
        <link href='<?php echo NC_UI_PATH; ?>/css/nc-ui.css' rel='stylesheet' type='text/css'>        
        <title>NetworkCurator <?php if ($network) echo ": ".$network; ?></title>
    </head>

    <body>
        <div id="page">
            <div class="container">
                                