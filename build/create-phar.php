<?php

/**
 * Creates bin/pharext, invoked through the Makefile
 */

set_include_path(dirname(__DIR__)."/src");
spl_autoload_register(function($c) {
	return include strtr($c, "\\_", "//") . ".php";
});

require_once __DIR__."/../src/pharext/Version.php";

$file = (new pharext\Task\PharBuild(null, [
	"header" => sprintf("pharext v%s (c) Michael Wallner <mike@php.net>", pharext\VERSION),
	"version" => pharext\VERSION,
	"name" => "pharext",
	"date" => date("Y-m-d"),
	"stub" => "pharext_packager.php",
	"license" => file_get_contents(__DIR__."/../LICENSE")
], false))->run();

if (getenv("SIGN")) {
	$pass = (new pharext\Task\Askpass)->run();
	$sign = new pharext\Task\PharSign($file, __DIR__."/pharext.key", $pass);
	$pkey = $sign->run();
	$pkey->exportPublicKey(__DIR__."/../bin/pharext.pubkey");
}

/* we do not need the extra logic of Task\PharRename */
rename($file, __DIR__."/../bin/pharext");
