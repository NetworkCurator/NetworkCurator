<?php

echo "signing out<br/>";

include_once "php/nc-sessions.php";
ncSignout();

echo "complete";
?>
