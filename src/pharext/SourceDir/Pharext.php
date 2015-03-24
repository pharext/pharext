<?php

namespace pharext\SourceDir;

use pharext\Command;
use pharext\Exception;
use pharext\SourceDir;

/**
 * A source directory containing pharext_package.php and eventually pharext_install.php
 */
class Pharext implements \IteratorAggregate, SourceDir
{
	/**
	 * @var pharext\Command
	 */
	private $cmd;
	
	/**
	 * @var string
	 */
	private $path;
	
	/**
	 * @var callable
	 */
	private $iter;

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
		public function __construct(Command $cmd, $path) {
		$this->cmd = $cmd;
		$this->path = $path;
		
		$callable = include "$path/pharext_package.php";
		if (!is_callable($callable)) {
			throw new Exception("Package hook did not return a callable");
		}
		$this->iter = $callable($cmd, $path);
	}

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getBaseDir()
	 */
	public function getBaseDir() {
		return $this->path;
	}

	/**
	 * Implements IteratorAggregate
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {
		if (!is_callable($this->iter)) {
			return new Git($this->cmd, $this->path);
		} 
		return call_user_func($this->iter, $this->cmd, $this->path);
	} 
}
