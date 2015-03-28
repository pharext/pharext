<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\Task;

/**
 * Fixup package.xml files in an extracted PECL dir
 */
class PeclFixup implements Task
{
	/**
	 * @var string
	 */
	private $source;

	/**
	 * @param string $source source directory
	 */
	public function __construct($source) {
		$this->source = $source;
	}

	/**
	 * @param bool $verbose
	 * @return string sanitized source location
	 * @throws \pahrext\Exception
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Sanitizing PECL dir ...\n");
		}
		$dirs = glob("{$this->source}/*", GLOB_ONLYDIR);
		$files = array_diff(glob("{$this->source}/*"), $dirs);

		if (count($dirs) !== 1 || !count($files)) {
			throw new Exception("Does not look like an extracted PECL dir: {$this->source}");
		}

		$dest = current($dirs);

		foreach ($files as $file) {
			if ($verbose) {
				printf("Moving %s into %s ...\n", basename($file), basename($dest));
			}
			if (!rename($file, "$dest/" . basename($file))) {
				throw new Exception;
			}
		}

		return $dest;
	}
}
