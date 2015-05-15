<?php

namespace pharext\SourceDir;

use pharext\Cli\Args;
use pharext\License;
use pharext\SourceDir;

use FilesystemIterator;
use IteratorAggregate;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;


class Basic implements IteratorAggregate, SourceDir
{
	use License;
	
	private $path;
	
	public function __construct($path) {
		$this->path = $path;
	}
	
	public function getBaseDir() {
		return $this->path;
	}
	
	public function getPackageInfo() {
		return [];
	}
	
	public function getLicense() {
		if (($file = $this->findLicense($this->getBaseDir()))) {
			return $this->readLicense($file);
		}
		return "UNKNOWN";
	}

	public function getArgs() {
		return [];
	}
	
	public function setArgs(Args $args) {
	}

	public function filter($current, $key, $iterator) {
		$sub = $current->getSubPath();
		if ($sub === ".git" || $sub === ".hg" || $sub === ".svn") {
			return false;
		}
		return true;
	}
	
	public function getIterator() {
		$rdi = new RecursiveDirectoryIterator($this->path,
				FilesystemIterator::CURRENT_AS_SELF | // needed for 5.5
				FilesystemIterator::KEY_AS_PATHNAME |
				FilesystemIterator::SKIP_DOTS);
		$rci = new RecursiveCallbackFilterIterator($rdi, [$this, "filter"]);
		$rii = new RecursiveIteratorIterator($rci);
		foreach ($rii as $path => $child) {
			if (!$child->isDir()) {
				yield $path;
			}
		}
	}
}
