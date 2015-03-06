<?php

namespace pharext;

class PharextSourceDir implements \IteratorAggregate, SourceDir
{
	private $cmd;
	private $path;
	private $iter;
	
	public function __construct(Command $cmd, $path) {
		$this->cmd = $cmd;
		$this->path = $path;
		
		$callable = include "$path/pharext_package.php";
		if (!is_callable($callable)) {
			throw new \Exception("Package hook did not return a callable");
		}
		$this->iter = $callable($cmd, $path);
	}
	
	public function getBaseDir() {
		return $this->path;
	}
	
	public function getIterator() {
		if (!is_callable($this->iter)) {
			throw new \Exception("Package hook callback did not return a callable");
		} 
		return call_user_func($this->iter, $this->cmd, $this->path);
	} 
}