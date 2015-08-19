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
		$temp = sys_get_temp_dir() . "/pharext-" . $this->getUser();
		if (!is_dir($temp) && !mkdir($temp, 0700, true)) {
			throw new Exception;
		}
		$this->name = $temp ."/". uniqid($prefix) . $suffix;
	}

	private function getUser() {
		if (extension_loaded("posix") && function_exists("posix_getpwuid")) {
			return posix_getpwuid(posix_getuid())["name"];
		}
		return trim(`whoami 2>/dev/null`)
			?: trim(`id -nu 2>/dev/null`)
			?: getenv("USER")
			?: get_current_user();
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return (string) $this->name;
	}
}
