<?php

/*
 * Script that tests helper class: SimpleMinifier
 * (For debugging and testing only)
 * 
 * 
 */

echo "\n";
echo "test-80-minify: tests code minification\n\n";
include_once "../tools/SimpleMinifier.php";
include_once "test-prep.php";



/* --------------------------------------------------------------------------
 * Create some identicon png files in current directory
 * -------------------------------------------------------------------------- */

$minifier = new SimpleMinifier();

// a set of inputs and expected outputs in 2D array
// each element in array is [input, expected_output]
$tests = [
    ["var i=0;", "var i=0;"],
    ["var i=0; // comment\n", "var i=0;"],
    ["var i=0; // comment\nvar j=0;", "var i=0;var j=0;"],
    ["/* this is a //comment// */\nvar i=0;", "var i=0;"]
];

// evaluate the tests one by one
for ($i = 0; $i < count($tests); $i++) {
    $onetest = $tests[$i];
    $indata = $onetest[0];
    $expected = $onetest[1];
    $minified = $minifier->minify($indata);

    echo "Testing minifier [$i]: ";
    comparereport($expected, $minified);
}
?>
