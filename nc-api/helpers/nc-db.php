<?php

/**
 * Collection of functions dealing with NetworkCurator database management
 * 
 *  
 */

/**
 * Create a connection to the NC database using PDO
 * 
 */
function connectDB($server, $dbname, $account, $password) {
    $db = new PDO('mysql:host=' . $server . ';dbname=' . $dbname . ';charset=utf8mb4',
                    $account, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}

?>
