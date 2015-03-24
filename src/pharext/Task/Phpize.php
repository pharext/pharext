<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\ExecCmd;
use pharext\Task;

/**
 * Run phpize in the extension source directory
 */
class Phpize implements Task
{
	/**
	 * @var string
	 */
	private $phpize;

	/**
	 *
	 * @var string
	 */
	private $cwd;

	/**
	 * @param string $cwd working directory
	 * @param string $prefix install prefix, e.g. /usr/local
	 * @param string $common_name PHP program common name, e.g. php5
	 */
	public function __construct($cwd, $prefix = null,  $common_name = "php") {
		$this->cwd = $cwd;
		$cmd = $common_name . "ize";
		if (isset($prefix)) {
			$cmd = $prefix . "/bin/" . $cmd;
		}
		$this->phpize = $cmd;
	}

	/**
	 * @param bool $verbose
	 * @throws \pharext\Exception
	 */
	public function run($verbose = false) {
		$pwd = getcwd();
		if (!chdir($this->cwd)) {
			throw new Exception;
		}
		try {
			$cmd = new ExecCmd($this->phpize, $verbose);
			$cmd->run();
		} finally {
			chdir($pwd);
		}
	}
}
