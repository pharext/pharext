<?php

namespace pharext;

use pharext\Exception;

/**
 * A temporary file/directory name
 */
class Tempname
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @param string $prefix uniqid() prefix
	 * @param string $suffix e.g. file extension
	 */
	public function __construct($prefix, $suffix = null) {
		$temp = sys_get_temp_dir() . "/pharext-" . posix_getlogin();
		if (!is_dir($temp) && !mkdir($temp, 0700, true)) {
			throw new Exception;
		}
		$this->name = $temp ."/". uniqid($prefix) . $suffix;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return (string) $this->name;
	}
}
