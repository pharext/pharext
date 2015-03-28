<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\ExecCmd;
use pharext\Task;

/**
 * Runs extension's configure
 */
class Configure implements Task
{
	/**
	 * @var array
	 */
	private $args;

	/**
	 * @var string
	 */
	private $cwd;

	/**
	 * @param string $cwd working directory
	 * @param array $args configure args
	 * @param string $prefix install prefix, e.g. /usr/local
	 * @param string $common_name PHP programs common name, e.g. php5
	 */
	public function __construct($cwd, array $args = null, $prefix = null, $common_name = "php") {
		$this->cwd = $cwd;
		$cmd = $common_name . "-config";
		if (isset($prefix)) {
			$cmd = $prefix . "/bin/" . $cmd;
		}
		$this->args =  ["--with-php-config=$cmd"];
		if ($args) {
			$this->args = array_merge($this->args, $args);
		}
	}

	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Running ./configure ...\n");
		}
		$pwd = getcwd();
		if (!chdir($this->cwd)) {
			throw new Exception;
		}
		try {
			$cmd = new ExecCmd("./configure", $verbose);
			$cmd->run($this->args);
		} finally {
			chdir($pwd);
		}
	}
}
