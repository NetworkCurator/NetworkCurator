<?php

/*
 * Post-installation script that tests email-sending capabilities
 * (For debugging and testing only)
 * 
 * The test will attempt to send an email to the admin user. You should verify
 * manually that the email has been received.
 * 
 */

echo "\n";
echo "test-21-email: tests for class NCEmail \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Fetch email
 * -------------------------------------------------------------------------- */

$emaildir = "../../nc-api/templates";

$ncemail = new NCEmail($db, $emaildir, "admin@".NC_SITE_DOMAIN);

$ncemail->sendEmailToUsers("email-test", ["userid"=>"admin"], ["admin"]);



?>
