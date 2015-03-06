# pharext

Distribute your PHP extension as self-installable phar executable

## About

### Disclaimer

You don't need this package to install any `*.ext.phar` extension packages,
just run them with php:

	$ ./pecl_http-2.4.0dev.ext.phar

For a compressed phar, or if the execute permission bit got lost somehow:

	$ php pecl_http-2.4.0dev.ext.phar.gz

Command help:

	$ ./pecl_http-2.4.0dev.ext.phar -h

Yields:

	pharext v1.1.0 (c) Michael Wallner <mike@php.net>
	
	Usage:
	
	  $ ./pecl_http-2.4.0dev.ext.phar [-hvqs] [-p|-n|-c <arg>]
	
	    -h|--help                                  Display help
	    -v|--verbose                               More output
	    -q|--quiet                                 Less output
	    -p|--prefix <arg>                          PHP installation prefix if phpize is not in $PATH, e.g. /opt/php7
	    -n|--common-name <arg>                     PHP common program name, e.g. php5 or zts-php [php]
	    -c|--configure <arg>                       Additional extension configure flags, e.g. -c --with-flag
	    -s|--sudo [<arg>]                          Installation might need increased privileges [sudo -S %s]
	    --with-http-zlib-dir [<arg>]               Where to find zlib [/usr]
	    --with-http-libcurl-dir [<arg>]            Where to find libcurl [/usr]
	    --with-http-libevent-dir [<arg>]           Where to find libevent [/usr]


If your installation destination needs escalated permissions, have a look at the `--sudo` option:

	$ ./pecl_http-2.4.0dev.ext.phar --sudo
	Installing pecl_http-2.4.0dev.ext.phar ... 
	Running phpize ... OK
	Running configure ... OK
	Running make ... OK
	Running install ... 
	Password:OK
	Cleaning up /tmp/pecl_http-2.4.0dev.ext.phar.54fa02e3316f7 ...
	Don't forget to activiate the extension in your php.ini!


### Prerequisites

The usual tools you need to build a PHP extension:
* php, phpize and php-config
* make, cc and autotools

A network connection is not needed.

## Download for extension maintainers

Download the pharext binary of the [latest release](https://github.com/m6w6/pharext/releases/latest).

## Installation for extension maintainers

	$ composer require --dev m6w6/pharext

### Prerequisites:

* make
* php + phar

## Usage


Command help:

	$ ./vendor/bin/pharext --help

Yields:

	pharext v1.1.0 (c) Michael Wallner <mike@php.net>
	
	Usage:
	
	  $ ./bin/pharext [-hvqgpzZ] -n <arg> -r <arg> -s <arg> [-d <arg>]
	
	    -h|--help                    Display this help
	    -v|--verbose                 More output
	    -q|--quiet                   Less output
	    -n|--name <arg>              Extension name (REQUIRED)
	    -r|--release <arg>           Extension release version (REQUIRED)
	    -s|--source <arg>            Extension source directory (REQUIRED)
	    -g|--git                     Use `git ls-files` instead of the standard ignore filter
	    -p|--pecl                    Use PECL package.xml instead of the standard ignore filter
	    -d|--dest <arg>              Destination directory [.]
	    -z|--gzip                    Create additional PHAR compressed with gzip
	    -Z|--bzip                    Create additional PHAR compressed with bzip

### PECL source dirs

PECL source dirs can infer package name, release version and file list
from the package.xml.

	$ ./vendor/bin/pharext --pecl --source ../pecl_http.git

Yields:

	Creating phar /tmp/54fa028adce40.phar ... OK
	Finalizing ./pecl_http-2.4.0dev.ext.phar ... OK

### GIT source dirs

Another example using --git (`git ls-files`):

	$ ./vendor/bin/pharext -v -g -s ../raphf.git --name raphf --release 1.0.5

Yields:

	Creating phar /tmp/54fa0455a9aee.phar ...
	Packaging .gitignore
	Packaging CREDITS
	Packaging Doxyfile
	Packaging LICENSE
	Packaging TODO
	Packaging config.m4
	Packaging config.w32
	Packaging package.xml
	Packaging php_raphf.c
	Packaging php_raphf.h
	Packaging raphf.png
	Packaging tests/http001.phpt
	Packaging tests/http002.phpt
	Packaging tests/http003.phpt
	Packaging tests/http004.phpt
	Created executable phar /tmp/54fa0455a9aee.phar
	Finalizing ./raphf-1.0.5.ext.phar ... OK

### Packager and installer hooks

#### Packager hook
If neither --pecl nor --git are explicitely given, pharext looks for a
`pharext_install.php` in --source. This script will be exectuted by the
Packager. It must return a callable with the following signature:

	function(Packager $pkg, $path) : function(Packager $pkg, $path);

So, the callback should return another callable.
The primary callback is meant to set things like --name and --release,
so you don't have to on the command line; build automation FTW.
The secondary callback is meant to create the file iterator of the
source dir, but if you're in a git clone, you might easily just return a
new pharext\GitSourceDir and be done.

##### Example for pecl_http

pharext_package.php

	<?php

	namespace pharext;

	return function(Packager $packager, $path) {
		$args = $packager->getArgs();
		$args->name = "pecl_http";
		$args->release = current(preg_filter("/^.*PHP_PECL_HTTP_VERSION\s+\"(.*)\".*$/s", "\$1", file("../http.git/php_http.h")));
		return function (Packager $packager, $path) {
			return new GitSourceDir($packager, $path);
		};
	};

#### Installer hook
The packager will look for a `pharext_install.php` file within the root of
the source dir. This script will be executed by the Installer; it must
return a callable with the following signature:

	function(Installer $installer) : function(Installer $installer);

So, again, the callback should return another callable.
The primary callback is meant to add your own command line arguments to
the CliArgs parser, and the secnodary callback is meant to proccess
those args.

##### Example for pecl_http

pharext_install.php

	<?php

	namespace pharext;

	return function(Installer $installer) {
		$installer->getArgs()->compile([
			[null, "with-http-zlib-dir", "Where to find zlib",
				CliArgs::OPTARG],
			[null, "with-http-libcurl-dir", "Where to find libcurl",
				CliArgs::OPTARG],
			[null, "with-http-libevent-dir", "Where to find libev{,ent{,2}}",
				CliArgs::OPTARG],
			[null, "with-http-libidn-dir", "Where to find libidn",
				CliArgs::OPTARG],
		]);

		return function(Installer $installer) {
			$opts = [
				"with-http-zlib-dir",
				"with-http-libcurl-dir",
				"with-http-libevent-dir",
				"with-http-libidn-dir",
			];
			$args = $installer->getArgs();
			foreach ($opts as $opt) {
				if (isset($args[$opt])) {
					$args->configure = "--$opt=".$args[$opt];
				}
			}
		};
	};

#### PECL source dirs
For --pecl source dirs a pharext_install.php script is automatically
generated from the package.xml which adds defined configure options
and automatically generates a file list.

## Rebuilding

	$ make -C vendor/m6w6/pharext
