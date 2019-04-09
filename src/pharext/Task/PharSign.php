<?php

namespace pharext\Task;

use pharext\Openssl;
use pharext\Task;

use Phar;

/**
 * Sign the phar with a private key
 */
class PharSign implements Task
{
	/**
	 * @var Phar
	 */
	private $phar;

	/**
	 * @var \pharext\Openssl\PrivateKey
	 */
	private $pkey;

	/**
	 *
	 * @param mixed $phar phar instance or path to phar
	 * @param string $pkey path to private key
	 * @param string $pass password for the private key
	 */
	public function __construct($phar, $pkey, $pass) {
		if ($phar instanceof Phar || $phar instanceof PharData) {
			$this->phar = $phar;
		} else {
			$this->phar = new Phar($phar);
		}
		$this->pkey = new Openssl\PrivateKey($pkey, $pass);
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Openssl\PrivateKey
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Signing %s ...\n", basename($this->phar->getPath()));
		}
		$this->pkey->sign($this->phar);
		return $this->pkey;
	}
}
