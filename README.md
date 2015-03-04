# pharext

Distribute your PHP extension as self-installable phar executable

## About

### Disclaimer

You don't need this package to install any `*.ext.phar` extension packages,
just run them with php:

	$ ./pecl_http-2.4.0dev.ext.phar

Or, if the execute permission bit got lost somehow:

	$ php pecl_http-2.4.0dev.ext.phar

Command help:

	$ ./pecl_http-2.4.0dev.ext.phar -h

Yields:

	Usage:
	
	  $ ./pecl_http-2.4.0dev.ext.phar [-h|-v|-q|-s] [-p|-n|-c <arg>]
	
	    -h|--help                    Display help 
	    -v|--verbose                 More output 
	    -q|--quiet                   Less output 
	    -p|--prefix <arg>            PHP installation directory  [/usr]
	    -n|--common-name <arg>       PHP common program name, e.g. php5  [php]
	    -c|--configure <arg>         Additional extension configure flags 
	    -s|--sudo [<arg>]            Installation might need increased privileges  [sudo -S %s]

If your installation destination needs escalated permissions, have a look at the `--sudo` option:

	$ ./pecl_http-2.4.0dev.ext.phar --sudo
	Running phpize ... OK
	Running configure ... OK
	Running make ... OK
	Running install ... Password:············
	Installing shared extensions:     /usr/lib/php/extensions/no-debug-non-zts-20121212/
	Installing header files:          /usr/include/php/
	OK

### Prerequisites

The usual tools you need to build a PHP extension:
* php, phpize and php-config
* make, cc and autotools
A network connection is not needed.

### Not implemented

* Dependencies
* Package description files

## Installation for extension maintainers

	$ composer require m6w6/pharext

### Prerequisites:

* make
* php + phar

## Usage

	$ ./bin/pharext --pecl --source ../pecl_http.git

Yields:

	Creating phar ./pecl_http-2.4.0dev.ext.phar.54f6e987ae00f.tmp ... OK
	Finalizing ./pecl_http-2.4.0dev.ext.phar ... OK

Note that the PECL source can infer package name and release version from the package.xml.

Another example using `git ls-files`:

	$ ./bin/pharext -v -g -s ../raphf.git --name raphf --release 1.0.5

Yields:

	Creating phar ./raphf-1.0.5.ext.phar.54f6ebd71f13b.tmp ...
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
	OK
	Finalizing ./raphf-1.0.5.ext.phar ... OK

Command help:

	$ ./bin/pharext --help

Yields:

	Usage:
	
	  $ ./bin/pharext [-h|-v|-q|-g|-p] -s <arg> -n <arg> -r <arg> [-d <arg>]
	
	    -h|--help                    Display this help 
	    -v|--verbose                 More output 
	    -q|--quiet                   Less output 
	    -s|--source <arg>            Extension source directory (REQUIRED)
	    -g|--git                     Use `git ls-files` instead of the standard ignore filter 
	    -p|--pecl                    Use PECL package.xml instead of the standard ignore filter 
	    -d|--dest <arg>              Destination directory  [.]
	    -n|--name <arg>              Extension name (REQUIRED)
	    -r|--release <arg>           Extension release version (REQUIRED)

