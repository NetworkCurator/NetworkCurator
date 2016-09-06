<?php

include_once "../helpers/NCIdenticons.php";

$igenerator= new NCIdenticons();

for ($i=0; $i<24; $i++) {
    imagepng($igenerator->getIdenticon(), "test-$i.png");
}

?>
