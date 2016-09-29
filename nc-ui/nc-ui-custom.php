<?php

/*
 * This is a location that can process instance-specific page requests,
 * e.g. about pages, help pages, contact pages, etc.
 * 
 */

if ($page == "sandbox") {
    if (isset($_REQUEST['sandbox'])) {
        $sandbox = $_REQUEST['sandbox'];
    }
    include_once "nc-ui/nc-components/ui-sandbox-index.php";
    exit();
}

// if reached here, there is something wrong
include_once "nc-ui/nc-components/ui-unknown.php";
?>

