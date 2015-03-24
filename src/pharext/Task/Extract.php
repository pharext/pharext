<?php

namespace pharext\Task;

use pharext\Task;
use pharext\Tempdir;

use Phar;
use PharData;

/**
 * Extract a package archive
 */
class Extract implements Task
{
	/**
	 * @var Phar(Data)
	 */
	private $source;

	/**
	 * @param mixed $source archive location
	 */
	public function __construct($source) {
		if ($source instanceof Phar || $source instanceof PharData) {
			$this->source = $source;
		} else {
			$this->source = new PharData($source);
		}
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Tempdir
	 */
	public function run($verbose = false) {
		$dest = new Tempdir("extract");
		$this->source->extractTo($dest);
		return $dest;
	}
}
