<?php 

namespace pharext;

class Tempfile extends \SplFileInfo
{
	private $handle;
	
	function __construct($prefix) {
		$tries = 0;
		/* PharData needs a dot in the filename, sure */
		$temp = sys_get_temp_dir() . "/";
		
		$omask = umask(077);
		do {
			$path = $temp.uniqid($prefix).".tmp";
			$this->handle = fopen($path, "x");
		} while (!is_resource($this->handle) && $tries++ < 10);
		umask($omask);
		
		if (!is_resource($this->handle)) {
			throw new \Exception("Could not create temporary file");
		}
		
		parent::__construct($path);
	}
	
	function __destruct() {
		@unlink($this->getPathname());
	}
	
	function closeStream() {
		fclose($this->handle);
	}

	function getStream() {
		return $this->handle;
	}
}
