<?php
/**
 * The installer sub-stub for extension phars
 */

function __autoload($c) {
	return include strtr($c, "\\_", "//") . ".php";
}

$installer = new pharext\Installer();
$installer->run($argc, $argv);
