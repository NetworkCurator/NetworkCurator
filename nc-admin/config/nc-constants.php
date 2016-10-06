<?php
// The following definitions are not meant to be changed by anyone after installation

// Definitions for constants used in annotations/values tables
define('NC_NAME',           0);
define('NC_TITLE',          1);
define('NC_ABSTRACT',       2);
define('NC_CONTENT',        3);
define('NC_DEFS',           5);
define('NC_UNIT',           10);
define('NC_COMMENT',        20);
define('NC_SUBCOMMENT',     21);

// Definitions for constants for status
define('NC_ACTIVE',         1);
define('NC_DEPRECATED',     -1);
define('NC_OLD',            0);

// Definitions for permission level
define('NC_PERM_NONE',      0);
define('NC_PERM_VIEW',      1);
define('NC_PERM_COMMENT',   2);
define('NC_PERM_EDIT',      3);
define('NC_PERM_CURATE',    4);
define('NC_PERM_SUPER',     9);

// Definitions for prefixes for various annotations
define('NC_PREFIX_NETWORK', 'W');
define('NC_PREFIX_CLASS',   'C');
define('NC_PREFIX_NODE',    'V');
define('NC_PREFIX_LINK',    'L');
define('NC_PREFIX_TEXT',    'T');
define('NC_PREFIX_NUMBER',  'N');
define('NC_PREFIX_FILE',    'F');


?>