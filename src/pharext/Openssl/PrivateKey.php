<?php

namespace pharext\Openssl;

class PrivateKey
{
	/**
	 * OpenSSL pkey resource
	 * @var resource
	 */
	private $key;

	/**
	 * Read a private key
	 * @param string $file
	 * @param string $password
	 * @throws \Exception
	 */
	function __construct($file, $password) {
		$this->key = openssl_pkey_get_private("file://$file", $password);
		if (!is_resource($this->key)) {
			throw new \Exception("Could not load private key");
		}
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
	 * @throws \Exception
	 */
	function exportPublicKey($file) {
		if (!file_put_contents("$file.tmp", openssl_pkey_get_details($this->key)["key"])
		||	!rename("$file.tmp", $file)
		) {
			throw new \Exception(error_get_last()["message"]);
		}
	}
}
