<?php

namespace pharext;

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
		$this->name = sys_get_temp_dir() . "/" . uniqid($prefix) . $suffix;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return (string) $this->name;
	}
}
