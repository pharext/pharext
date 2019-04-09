<?php

namespace pharext\Task;

use pharext\Task;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * List all library files of pharext to bundle with a phar
 */
class BundleGenerator implements Task
{
	/**
	 * @param bool $verbose
	 * @return Generator
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Packaging pharext ... \n");
		}
		$rdi = new RecursiveDirectoryIterator(dirname(dirname(__DIR__)));
		$rii = new RecursiveIteratorIterator($rdi);
		for ($rii->rewind(); $rii->valid(); $rii->next()) {
			if (!$rii->isDot()) {
				yield $rii->getSubPathname() => $rii->key();
			}
		}
	}
}
