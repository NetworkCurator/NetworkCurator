<?php

/*
 * Helper functions to deal with node and link class structure
 */




/**
 * 
 * @param string $js - name of js object to assign to (e.g. nc.md)
 * @param object $arr - php array/object.
 * 
 * @return
 * 
 * a string with a <script> tag encoding the array into js
 */
function ncScriptObject($js, $arr) {      
    return "<script>$js = ".  json_encode($arr)."; </script>";
}

?>