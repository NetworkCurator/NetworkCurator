<?php
/*
 * Page for administering networks
 * 
 */

// This page should only be visible to the admin user
if ($_SESSION['uid'] !== "admin") {
    header("Refresh: 0; ?page=front");
    exit();
}

if (isset($_REQUEST['new'])) {
    if ($_REQUEST['new'] === "user") {
        include_once "nc-components/ui-newuser-form.php";
    } else {
        include_once "nc-components/ui-newnetwork-form.php";
    }
} else if ($network) {    
    include_once "nc-components/ui-network-config.php";
} else {
    echo "<p>Why are we here? Someting missing in the url.</p>";
}

?>    


