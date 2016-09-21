<?php

// Definitions for annotations/values tables
define('NC_NAME',	0);
define('NC_TITLE',	1);
define('NC_ABSTRACT',	2);
define('NC_CONTENT',	3);
define('NC_UNIT',	10);
define('NC_COMMENT',	20);
define('NC_SUBCOMMENT',	21);

// Definitions for object types
define('NC_NETWORK',    0);
define('NC_CLASS',	1);
define('NC_NODE',	2);
define('NC_LINK',	3);
define('NC_ANNOTEXT',   4);
define('NC_ANNONUM',    5);

// Definitions for users
define('NC_USER_ADMIN', -1);
define('NC_USER_GUEST', 0);

// Definitions for status
define('NC_ACTIVE',	1);
define('NC_DEPRECATED',	-1);
define('NC_OLD',	0);

// Definitions for permission level
define('NC_PERM_NONE',	0);
define('NC_PERM_VIEW',	1);
define('NC_PERM_COMMENT',2);
define('NC_PERM_EDIT',	3);
define('NC_PERM_CURATE',4);
define('NC_PERM_SUPER',	9);

?>