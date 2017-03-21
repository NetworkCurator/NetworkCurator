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
    return "<script>$js = " . json_encode($arr) . "; </script>";
}


/**
 * check if a local version of a file exists.
 * 
 * e.g. input $path = "file.php"
 * checks if file-local.php exists. 
 * If yes, returns file-local.php.
 * If no, returns file.php
 *  
 * @param type $path
 */
function ncGetLocalFile($path) {    
    $pathend = substr($path, -4);
    $localfile = substr($path, 0, -4) . "-local" . $pathend;  
    if (file_exists($localfile)) {        
        return $localfile;        
    } else {        
        return $path;        
    }    
}

?>