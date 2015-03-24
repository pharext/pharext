<?php

namespace pharext;

/**
 * Create a temporary directory
 */
class Tempdir extends \SplFileInfo
{
	/**
	 * @param string $prefix prefix to uniqid()
	 * @throws \pharext\Exception
	 */
	public function __construct($prefix) {
		$temp = new Tempname($prefix);
		if (!is_dir($temp) && !mkdir($temp, 0700, true)) {
			throw new Exception("Could not create tempdir: ".error_get_last()["message"]);
		}
		parent::__construct($temp);
	}
}
