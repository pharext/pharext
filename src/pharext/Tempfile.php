<?php 

namespace pharext;

/**
 * Create a new temporary file
 */
class Tempfile extends \SplFileInfo
{
	/**
	 * @var resource
	 */
	private $handle;

	/**
	 * @param string $prefix uniqid() prefix
	 * @param string $suffix e.g. file extension
	 * @throws \pharext\Exception
	 */
	public function __construct($prefix, $suffix = ".tmp") {
		$tries = 0;
		$omask = umask(077);
		do {
			$path = new Tempname($prefix, $suffix);
			$this->handle = fopen($path, "x");
		} while (!is_resource($this->handle) && $tries++ < 10);
		umask($omask);
		
		if (!is_resource($this->handle)) {
			throw new Exception("Could not create temporary file");
		}
		
		parent::__construct($path);
	}

	/**
	 * Unlink the file
	 */
	public function __destruct() {
		@unlink($this->getPathname());
	}

	/**
	 * Close the stream
	 */
	public function closeStream() {
		fclose($this->handle);
	}

	/**
	 * Retrieve the stream resource
	 * @return resource
	 */
	public function getStream() {
		return $this->handle;
	}
}
