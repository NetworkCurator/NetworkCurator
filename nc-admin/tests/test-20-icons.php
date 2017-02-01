<?php

/*
 * Post-installation script that tests helper class: NCIdenticons
 * (For debugging and testing only)
 * 
 * This test generates some identicons in the current directory.
 * After running the script, check the image files and delete them manually. 
 * 
 */

echo "\n";
echo "test-20-icons: tests identicon generation\n\n";
include_once "../../nc-api/helpers/NCIdenticons.php";



/* --------------------------------------------------------------------------
 * Create some identicon png files in current directory
 * -------------------------------------------------------------------------- */

$igenerator= new NCIdenticons();

for ($i=0; $i<24; $i++) {
    imagepng($igenerator->getIdenticon(), "zz-identicon-$i.png");
}


?>
