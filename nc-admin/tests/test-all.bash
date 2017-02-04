#!/bin/bash

## Run all the test scripts


php test-01-create.php
php test-02-networks.php
php test-03-permissions.php
php test-04-ontology.php
php test-05-graph.php

php test-10-import.php
php test-11-update.php

## test for tools
php test-80-minify.php


## By default, the script also runs the purge component
## This removes users, networks, objects created during testing
## To see those objects in the GUI, and thus check proper behavior,
## comment the next line.
##php test-99-purge.php

