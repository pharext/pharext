# pharext

[![Join the chat at https://gitter.im/m6w6/pharext](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/m6w6/pharext?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Distribute your PHP extension as self-installable phar executable

## About

You don't need this package to install any `*.ext.phar` extension packages,
just run them with php:

	$ ./pecl_http-2.4.2.ext.phar

For a compressed phar, or if the execute permission bit got lost somehow:

	$ php pecl_http-2.4.2.ext.phar.gz

Command help:

	$ ./pecl_http-2.4.2.ext.phar -h

Yields:

	pharext v3.0.0 (c) Michael Wallner <mike@php.net>

	Usage:

	$ ./pecl_http-2.4.2.ext.phar [-hvqs] [-p|-n|-c|-i <arg>]

	  -h|--help                                  Display help
	  -v|--verbose                               More output
	  -q|--quiet                                 Less output
	  -p|--prefix <arg>                          PHP installation prefix if phpize is not in $PATH, e.g. /opt/php7
	  -n|--common-name <arg>                     PHP common program name, e.g. php5 or zts-php [php]
	  -c|--configure <arg>                       Additional extension configure flags, e.g. -c --with-flag
	  -s|--sudo [<arg>]                          Installation might need increased privileges [sudo -S %s]
	  -i|--ini <arg>                             Activate in this php.ini instead of loaded default php.ini
	  --signature                                Show package signature
	  --license                                  Show package license
	  --name                                     Show package name
	  --date                                     Show package release date
	  --release                                  Show package release version
	  --version                                  Show pharext version
	  --enable-propro [<arg>]                    Whether to enable property proxy support [yes]
	  --enable-raphf [<arg>]                     Whether to enable raphf support [yes]
	  --with-http-zlib-dir [<arg>]               Where to find zlib [/usr]
	  --with-http-libcurl-dir [<arg>]            Where to find libcurl [/usr]
	  --with-http-libevent-dir [<arg>]           Where to find libevent [/usr]


If your installation destination needs escalated permissions, have a look at [the `--sudo` option](https://github.com/m6w6/pharext/wiki/Usage-of-*.ext.phar-packages#privileges):

	Installing propro-1.0.1.ext.phar ...
	Running phpize ...
	Running configure ...
	Running make ...
	Running make install ...
	Running INI activation ...
	Extension already activated ...
	Successfully installed propro-1.0.1.ext.phar!
	Installing raphf-1.0.5.ext.phar ...
	Running phpize ...
	Running configure ...
	Running make ...
	Running make install ...
	Running INI activation ...
	Extension already activated ...
	Successfully installed raphf-1.0.5.ext.phar!
	Installing pecl_http-2.4.2.ext.phar ...
	Running phpize ...
	Running configure ...
	Running make ...
	Running make install ...
	Running INI activation ...
	Extension already activated ...
	Successfully installed pecl_http-2.4.2.ext.phar!


### Prerequisites

The usual tools you need to build a PHP extension:
* php, phpize and php-config
* make, cc and autotools

A network connection is not needed.

## Extension maintainers

Download the pharext binary of the [latest release](https://github.com/m6w6/pharext/releases/latest).

Be aware that you need the [public key](https://github.com/m6w6/pharext/wiki/Public-key) to run official `pharext` releases.

	-----BEGIN PUBLIC KEY-----
	MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA5x9bwisjDBDV/bwDiju2
	Ebx4kPir32WwT3+hxV0/qAPclA1WsrpcUJ7BChk+Rlz8ujOcyENTidgI1vj3oUpo
	/P9XlLQOSrJHYz+AOg7qwhTe89xIJspS4gHHiXUAmxz0TyCNMbOyrLcjP5CmZdll
	n+e3HP8Kfipr4XyWBhsKbdYUZ8Ga6IeFMYzNqCzWazcOasdCpsablmyrfCaZoJ0l
	bFald0nF3/YoeYgo3fWb4Md9Xf/grpz8Ocqyq4OY49Vb0/p8FMwzBV6vbVh/eAV/
	jrP7L40Jw97nSBrP/5nK8Ylc5BayVRq/HhT3kLMC//zvPjb8xz3ZgVTQrwWTF3Zy
	+wIDAQAB
	-----END PUBLIC KEY-----

Place it as `pharext.pubkey` in the same directory where the `pharext` binary is located. IF you cloned the repository or installed `pharext` through composer, it is already at the right location.

Please have a look at the [wiki](https://github.com/m6w6/pharext/wiki), to learn [how to use](https://github.com/m6w6/pharext/wiki/Usage-of-the-pharext-packager) the pharext installer to package self-installing PHP extensions.

