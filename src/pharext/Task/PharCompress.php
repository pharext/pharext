<?php

namespace pharext\Task;

use pharext\Task;

use Phar;

/**
 * Clone a compressed copy of a phar
 */
class PharCompress implements Task
{
	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var Phar
	 */
	private $package;

	/**
	 * @var int
	 */
	private $encoding;

	/**
	 * @var string
	 */
	private $extension;

	/**
	 * @param string $file path to the original phar
	 * @param int $encoding Phar::GZ or Phar::BZ2
	 */
	public function __construct($file, $encoding) {
		$this->file = $file;
		$this->package = new Phar($file);
		$this->encoding = $encoding;

		switch ($encoding) {
			case Phar::GZ:
				$this->extension = ".gz";
				break;
			case Phar::BZ2:
				$this->extension = ".bz2";
				break;
		}
	}

	/**
	 * @param bool $verbose
	 * @return string
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Compressing %s ...\n", basename($this->package->getPath()));
		}
		/* stop shebang */
		$stub = $this->package->getStub();
		$phar = $this->package->compress($this->encoding);
		$phar->setStub(substr($stub, strpos($stub, "\n")+1));
		return $this->file . $this->extension;
	}
}
