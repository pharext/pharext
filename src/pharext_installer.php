<?php
/**
 * The installer sub-stub for extension phars
 */

spl_autoload_register(function($c) {
	return include strtr($c, "\\_", "//") . ".php";
});

$installer = new pharext\Installer();
$installer->run($argc, $argv);
