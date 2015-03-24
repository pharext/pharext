<?php

namespace pharext\Openssl;

use pharext\Exception;

class PrivateKey
{
	/**
	 * Private key
	 * @var string
	 */
	private $key;
	
	/**
	 * Public key
	 * @var string
	 */
	private $pub;

	/**
	 * Read a private key
	 * @param string $file
	 * @param string $password
	 * @throws \pharext\Exception
	 */
	function __construct($file, $password) {
		/* there appears to be a bug with refcount handling of this
		 * resource; when the resource is stored as property, it cannot be
		 * "coerced to a private key" on openssl_sign() later in another method
		 */
		$key = openssl_pkey_get_private("file://$file", $password);
		if (!is_resource($key)) {
			throw new Exception("Could not load private key");
		}
		openssl_pkey_export($key, $this->key);
		$this->pub = openssl_pkey_get_details($key)["key"];
	}

	/**
	 * Sign the PHAR
	 * @param \Phar $package
	 */
	function sign(\Phar $package) {
		$package->setSignatureAlgorithm(\Phar::OPENSSL, $this->key);
	}

	/**
	 * Export the public key to a file
	 * @param string $file
	 * @throws \pharext\Exception
	 */
	function exportPublicKey($file) {
		if (!file_put_contents("$file.tmp", $this->pub) || !rename("$file.tmp", $file)) {
			throw new Exception;
		}
	}
}
