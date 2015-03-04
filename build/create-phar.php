<?php

/**
 * Creates bin/pharext, invoked through the Makefile
 */

$pkguniq = uniqid();
$pkgname = __DIR__."/../bin/pharext";
$tmpname = "$pkgname.$pkguniq.phar.tmp";

if (file_exists($tmpname)) {
	if (!unlink($tmpname)) {
		fprintf(STDERR, "%s\n", error_get_last()["message"]);
		exit(3);
	}
}

$package = new \Phar($tmpname, 0, "pharext.phar");
$package->buildFromDirectory(dirname(__DIR__)."/src", "/^.*\.php$/");
$package->setDefaultStub("pharext_packager.php");
$package->setStub("#!/usr/bin/php -dphar.readonly=0\n".$package->getStub());
unset($package);

if (!rename($tmpname, $pkgname)) {
	fprintf(STDERR, "%s\n", error_get_last()["message"]);
	exit(4);
}
