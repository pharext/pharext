<?php
/**
 * The packager sub-stub for bin/pharext
 */

function __autoload($c) {
	return include strtr($c, "\\_", "//") . ".php";
}

$packager = new pharext\Packager();
$packager->run($argc, $argv);
