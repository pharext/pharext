<?php

/**
 * The packager stub for bin/pharext
 */

#Phar::mapPhar("pharext.phar");

function __autoload($c) {
	return include /*"phar://pharext.phar/".*/strtr($c, "\\_", "//") . ".php";
}

$packager = new pharext\Packager();
$packager->run($argc, $argv);

__HALT_COMPILER();
