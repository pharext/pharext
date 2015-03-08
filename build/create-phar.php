<?php

/**
 * Creates bin/pharext, invoked through the Makefile
 */

$pkgname = __DIR__."/../bin/pharext";
$tmpname = __DIR__."/pharext.phar";

if (file_exists($tmpname)) {
	if (!unlink($tmpname)) {
		fprintf(STDERR, "%s\n", error_get_last()["message"]);
		exit(3);
	}
}

$package = new \Phar($tmpname, 0, "pharext.phar");

if (getenv("SIGN")) {
	shell_exec("stty -echo");
	printf("Password: ");
	$password = fgets(STDIN, 1024);
	printf("\n");
	shell_exec("stty echo");
	if (substr($password, -1) == "\n") {
		$password = substr($password, 0, -1);
	}
	
	$pkey = openssl_pkey_get_private("file://".__DIR__."/pharext.key", $password);
	if (!is_resource($pkey)) {
		$this->error("Could not load private key %s/pharext.key", __DIR__);
		exit(3);
	}
	if (!openssl_pkey_export($pkey, $key)) {
		$this->error(null);
		exit(3);
	}
	
	$package->setSignatureAlgorithm(Phar::OPENSSL, $key);
}

$package->buildFromDirectory(dirname(__DIR__)."/src", "/^.*\.php$/");
$package->setDefaultStub("pharext_packager.php");
$package->setStub("#!/usr/bin/php -dphar.readonly=0\n".$package->getStub());
unset($package);

if (!rename($tmpname, $pkgname)) {
	fprintf(STDERR, "%s\n", error_get_last()["message"]);
	exit(4);
}
