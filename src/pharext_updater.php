#!/usr/bin/php -dphar.readonly=0
<?php

/**
 * The installer updater stub for extension phars
 */

namespace pharext;

spl_autoload_register(function($c) {
	return include strtr($c, "\\_", "//") . ".php";
});

set_include_path('phar://' . __FILE__ .":". get_include_path());

if (!extension_loaded("Phar")) {
	fprintf(STDERR, "ERROR: Phar extension not loaded\n\n");
	fprintf(STDERR, "\tPlease load the phar extension in your php.ini\n".
					"\tor rebuild PHP with the --enable-phar flag.\n\n");
	exit(1);
}

if (ini_get("phar.readonly")) {
	fprintf(STDERR, "ERROR: Phar is configured read-only\n\n");
	fprintf(STDERR, "\tPlease specify phar.readonly=0 in your php.ini\n".
					"\tor run this command with php -dphar.readonly=0\n\n");
	exit(1);
}

\Phar::interceptFileFuncs();
\Phar::mapPhar();

$updater = new Updater();
$updater->run($argc, $argv);

__HALT_COMPILER();
