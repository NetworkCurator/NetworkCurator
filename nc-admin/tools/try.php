<?php

$params = ["AB"=>4, "BC"=>0];
print_r($params);

$bb = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, "a72930bd82ef001f", json_encode($params), MCRYPT_MODE_ECB));
echo $bb. "\n";

?>
