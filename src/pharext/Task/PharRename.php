<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\Task;

/**
 * Rename the phar archive
 */
class PharRename implements Task
{
	/**
	 * @var string
	 */
	private $phar;

	/**
	 * @var string
	 */
	private $dest;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @param string $phar path to phar
	 * @param string $dest destination dir
	 * @param string $name package name
	 */
	public function __construct($phar, $dest, $name) {
		$this->phar = $phar;
		$this->dest = $dest;
		$this->name = $name;
	}

	/**
	 * @param bool $verbose
	 * @return string path to renamed phar
	 * @throws \pharext\Exception
	 */
	public function run($verbose = false) {
		$extension = substr(strstr($this->phar, "-pharext.phar"), 8);
		$name = sprintf("%s/%s.ext%s", $this->dest, $this->name, $extension);

		if (!rename($this->phar, $name)) {
			throw new Exception;
		}

		return $name;
	}
}
