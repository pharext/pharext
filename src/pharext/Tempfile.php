<?php 

namespace pharext;

class Tempfile extends \SplFileInfo
{
	private $handle;
	
	function __construct($prefix) {
		$tries = 0;
		$template = sys_get_temp_dir()."/$prefix.";
		
		$omask = umask(077);
		do {
			$path = $template.uniqid();
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
