<?php
/**
 * The packager sub-stub for bin/pharext
 */
spl_autoload_register(function($c) {
	return include strtr($c, "\\_", "//") . ".php";
});

$packager = new pharext\Packager();
$packager->run($argc, $argv);
