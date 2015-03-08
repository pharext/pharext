<?php

namespace pharext\Openssl;

class PrivateKey
{
	private $key;
	
	function __construct($file, $password) {
		$this->key = openssl_pkey_get_private("file://$file", $password);
		if (!is_resource($this->key)) {
			throw new \Exception("Could not load private key");
		}
	}
	
	function sign(\Phar $package) {
		$package->setSignatureAlgorithm(\Phar::OPENSSL, $this->key);
	}
	
	function exportPublicKey($file) {
		if (!file_put_contents("$file.tmp", openssl_pkey_get_details($this->key)["key"])
		||	!rename("$file.tmp", $file)
		) {
			throw new \Exception(error_get_last()["message"]);
		}
	}
}
