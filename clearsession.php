<?php

echo "signing out<br/>";

include_once "nc-core/php/nc-sessions.php";
ncSignout();

echo "complete";
?>
