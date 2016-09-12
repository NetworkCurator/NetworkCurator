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
    return makeRandomString($stringlength, $okchars="1234567890abcdef");
}


/**
 * Generate a random string composed of characters
 * 
 * @param integer $stringlength
 * @param string $okchars
 * 
 * string with characters that are allowed in the output random string. By 
 * default the string holds alphanumeric characters without vowels. This 
 * helps avoid 'funny' random string like 'poop'.
 * 
 * @return string
 */
function makeRandomString($stringlength, $okchars="1234567890bcdfghjklmnpqrstvwxz") {

    // helper object 
    $oklen = strlen($okchars);

    // generate random string one character at a time
    $ans = "";
    $anslen = 0;
    while ($anslen < $stringlength) {
        $temppos = rand(0, $oklen - 1);
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
 * The function assumes the array has components firstname, user_middlename
 * and user_lastname
 * 
 * @param string $prefix
 * 
 * A prefix for the array components, e.g. lets concatentation work on 
 * arrays with, say, "target_firstname" etc. by setting $prefix="target_"
 * 
 * @return type
 */
function ncFullname($a, $prefix = "user_") {
    $firstname = $a[$prefix . 'firstname'];
    $middlename = $a[$prefix . 'middlename'];
    $lastname = $a[$prefix . 'lastname'];
    if ($middlename !== "") {
        return "$firstname $middlename $lastname";
    } else {
        return "$firstname $lastname";
    }
}


/**
 * concatenames user names from an array
 * 
 * @param type $a
 * @return type
 */
function ncListNames($a) {    
    $ans = "";
    for ($x=0; $x<count($a); $x++) {
        if ($x>0) {
         $ans .=", ";   
        }
        $ans .= ncFullname($a[$x]);
    }
    return $ans;
}


/**
 * Get a small array using only a few elements from a larger (assoc) array
 * 
 * @param array $array
 * @param array $keys
 * @return array
 * 
 */
function ncSubsetArray($array, $keys) {
    return array_intersect_key($array, array_flip($keys));
}


/**
 * Replaces any parameter placeholders in a query with the value of that
 * parameter. Useful for debugging. Assumes anonymous parameters from 
 * $params are are in the same order as specified in $query
 *
 * Code from "bigwebguy" at
 * http://stackoverflow.com/questions/210564/getting-raw-sql-query-string-from-pdo-prepared-statements
 * 
 * @param string $query The sql query with parameter placeholders
 * @param array $params The array of substitution parameters
 * @return string The interpolated query
 */
function ncInterpolateQuery($query, $params) {
       
    $keys = array();
    
    # build a regular expression for each parameter
    foreach ($params as $key => $value) {
        if (is_string($key)) {
            $keys[] = '/:'.$key.'/';
        } else {
            $keys[] = '/[?]/';
        }
    }

    $query = preg_replace($keys, $params, $query, 1, $count);

    #trigger_error('replaced '.$count.' keys');

    return $query;
}


?>
