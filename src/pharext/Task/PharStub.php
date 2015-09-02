<?php

namespace pharext\Task;

use Phar;
use pharext\Exception;
use pharext\Task;

/**
 * Set the phar's stub
 */
class PharStub implements Task
{
	/**
	 * @var \Phar
	 */
	private $phar;

	/**
	 * @var string
	 */
	private $stub;

	/**
	 * @param \Phar $phar
	 * @param string $stub file path to the stub
	 * @throws \pharext\Exception
	 */
	function __construct(Phar $phar, $stub) {
		$this->phar = $phar;
		if (!file_exists($this->stub = $stub)) {
			throw new Exception("File '$stub' does not exist");
		}
	}

	/**
	 * @param bool $verbose
	 */
	function run($verbose = false) {
		if ($verbose) {
			printf("Using stub '%s'...\n", basename($this->stub));
		}
		$stub = preg_replace_callback('/^#include <([^>]+)>/m', function($includes) {
			return file_get_contents($includes[1], true, null, 5);
		}, file_get_contents($this->stub));
		if ($this->phar->isCompressed() && substr($stub, 0, 2) === "#!") {
			$stub = substr($stub, strpos($stub, "\n")+1);
		}
		$this->phar->setStub($stub);
	}
}
