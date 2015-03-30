<?php

namespace pharext\SourceDir;

use pharext\Cli\Args;
use pharext\SourceDir;

use FilesystemIterator;
use IteratorAggregate;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;


class Basic implements IteratorAggregate, SourceDir
{
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
	
	public function getArgs() {
		return [];
	}
	
	public function setArgs(Args $args) {
	}
	
	public function getIterator() {
		$rdi = new RecursiveDirectoryIterator($this->path,
				FilesystemIterator::CURRENT_AS_SELF | // needed for 5.5
				FilesystemIterator::KEY_AS_PATHNAME |
				FilesystemIterator::SKIP_DOTS);
		$rii = new RecursiveIteratorIterator($rdi,
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($rii as $path => $child) {
			if (!$child->isDir()) {
				yield $path;
			}
		}
	}
}
