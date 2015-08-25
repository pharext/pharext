#!/usr/bin/env php
<?php

/**
 * The installer sub-stub for extension phars
 */

namespace pharext;

define("PHAREXT_PHAR", __FILE__);

spl_autoload_register(function($c) {
	return include strtr($c, "\\_", "//") . ".php";
});

#include <pharext/Exception.php>
#include <pharext/Tempname.php>
#include <pharext/Tempfile.php>
#include <pharext/Tempdir.php>
#include <pharext/Archive.php>

namespace pharext;

if (extension_loaded("Phar")) {
	\Phar::interceptFileFuncs();
	\Phar::mapPhar();
	$phardir = "phar://".__FILE__;
} else {
	$archive = new Archive(__FILE__);
	$phardir = $archive->extract();
}

set_include_path("$phardir:". get_include_path());

$installer = new Installer();
$installer->run($argc, $argv);

__HALT_COMPILER();
