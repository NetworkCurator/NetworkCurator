<?php

/*
 * Post-installation script that tests email-sending capabilities
 * (For debugging and testing only)
 * 
 */

echo "\n";
echo "test-21-email: tests for class NCEmail \n\n";
include_once "test-prep.php";


/* --------------------------------------------------------------------------
 * Fetch email
 * -------------------------------------------------------------------------- */

$emaildir = "../../nc-api/templates";

$ncemail = new NCEmail($db, $emaildir, "tokonopka@gmail.com");

$ncemail->sendEmailToUsers("email-test", ["userid"=>"admin"], ["admin", "alpha"]);



?>
