<?php
/*
 * Page for user account maintenance
 * 
 */

// This page should only be visible to the admin user
if ($uid == "guest") {
    header("Refresh: 0; ?page=front");
    exit();
}

include_once "nc-components/ui-user-info.php";

?>    


