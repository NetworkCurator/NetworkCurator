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
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/sanitize-html-1.13.0/sanitize-html.min.js'></script>
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/d3-4.2.2/d3.min.js'></script>        
        <script type='text/javascript' src='<?php echo NC_INCLUDES_PATH; ?>/makealive/makealive-lib.min.js'></script>                
        
        <script type='text/javascript' src='<?php echo NC_JS_PATH; ?>/networkcurator.js'></script> 
<?php
$firstname = $_SESSION['user_firstname'];
$middlename = $_SESSION['user_middlename'];
$lastname = $_SESSION['user_lastname'];
echo "<script>
nc.userid='$uid', 
nc.firstname='$firstname', 
nc.middlename='$middlename', 
nc.lastname='$lastname',
nc.network='$network',
nc.curator=". (int) ($iscurator == true) . ",
nc.editor=". (int) ($iseditor == true) .", 
nc.commentator=". (int) ($iscommentator == true) . ";
</script>";
?>
        
        <link rel='stylesheet' href='<?php echo NC_INCLUDES_PATH; ?>/bootstrap-3.3.7/css/bootstrap.min.css'>        
        <link rel='stylesheet' href='<?php echo NC_CSS_PATH; ?>/nc-core.css'>     
        <link href="https://fonts.googleapis.com/css?family=Open+Sans|Roboto:300,700" rel="stylesheet">
        <link href='<?php echo NC_UI_PATH; ?>/css/nc-ui.css' rel='stylesheet' type='text/css'>        
        <title>NetworkCurator <?php if ($network) echo ": " . $network; ?></title>
    </head>

    <body>
        <div id="page">
            <div class="container">
