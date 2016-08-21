<?php

/*
 * Generic functions used across the site 
 * 
 */

/**
 * Generate a random string of characters (e.g. a password). 
 * Input: length of the desired random string.
 * Output: random string.
 * 
 */
function makeRandomHexString($stringlength) {

    // the random string will be composed of hex digits 
    // (The ! at the end is actually not used in the random character picking)
    $okchars = "1234567890abcdef!";
    $oklen = strlen($okchars);

    // generate random string one character at a time
    $ans = "";
    $anslen = 0;
    while ($anslen < $stringlength) {
        $temppos = rand(0, $oklen - 2);
        $ans .= substr($okchars, $temppos, 1);
        $anslen++;
    }

    return $ans;
}


/**
 * Get a string with the username's full name from an array
 * 
 * @param array $a 
 * 
 * The function assumes the array has components user_firstname, user_middlename
 * and user_lastname
 * 
 * @return type
 */
function ncFullname($a) {
    $firstname = $a['user_firstname'];
    $middlename = $a['user_middlename'];
    $lastname = $a['user_lastname'];
    if ($middlename !== "") {
        return "$firstname $middlename $lastname";
    } else {
        return "$firstname $lastname";
    }
}

/**
 * Create an id string that is not already present in a dbtable table
 * 
 * @param type $conn
 * 
 * connection to database
 * 
 * @param type $dbtable
 * 
 * name of table in database to query
 * 
 * @param type $idcolumn
 * 
 * column in dbtable holding ids.
 * 
 * @param type $idprefix
 * 
 * prefix for random id - e.g. to make Nxxxxxx for nodes or Lxxxxxx for links
 * 
 * @param type $stringlength
 * 
 * integer, number of hex digits in the random id (excluding prefix)
 * 
 */
function makeRandomID($conn, $dbtable, $idcolumn, $idprefix, $stringlength) {

    $newid = "";
    $foundit = false;
    while (!$foundit) {
        $newid = $idprefix . makeRandomHexString($stringlength);
        $sql = "SELECT $idcolumn FROM $dbtable WHERE $idcolumn='$newid'";
        $sqlresult = mysqli_query($conn, $sql);
        $foundit = (mysqli_num_rows($sqlresult) == 0);
    }
    
    return $newid;
}

?>
