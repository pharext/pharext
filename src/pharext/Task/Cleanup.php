<?php

namespace pharext\Task;

use pharext\Task;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Recursively cleanup FS entries
 */
class Cleanup implements Task
{
	/**
	 * @var string
	 */
	private $rm;

	public function __construct($rm) {
		$this->rm = $rm;
	}

	/**
	 * @param bool $verbose
	 */
	public function run($verbose = false) {
		if ($verbose) {
			printf("Cleaning up %s ...\n", $this->rm);
		}
		if ($this->rm instanceof Tempfile) {
			unset($this->rm);
		} elseif (is_dir($this->rm)) {
			$rdi = new RecursiveDirectoryIterator($this->rm,
				FilesystemIterator::CURRENT_AS_SELF | // needed for 5.5
				FilesystemIterator::KEY_AS_PATHNAME |
				FilesystemIterator::SKIP_DOTS);
			$rii = new RecursiveIteratorIterator($rdi,
				RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($rii as $path => $child) {
				if ($child->isDir()) {
					rmdir($path);
				} else {
					unlink($path);
				}
			}
			rmdir($this->rm);
		} else {
			@unlink($this->rm);
		}
	}
}
