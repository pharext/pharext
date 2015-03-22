<?php

namespace pharext;

class Tempdir extends \SplFileInfo
{
	private $dir;
	
	public function __construct($prefix) {
		$temp = sprintf("%s/%s", sys_get_temp_dir(), uniqid($prefix));
		if (!is_dir($temp)) {
			if (!mkdir($temp, 0700, true)) {
				throw new Exception("Could not create tempdir: ".error_get_last()["message"]);
			}
		}
		parent::__construct($temp);
	}
}
