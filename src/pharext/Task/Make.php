<?php

namespace pharext\Task;

use pharext\ExecCmd;
use pharext\Exception;
use pharext\Task;

/**
 * Run make in the source dir
 */
class Make implements Task
{
	/**
	 * @var string
	 */
	private $cwd;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * @var string
	 */
	private $sudo;

	/**
	 *
	 * @param string $cwd working directory
	 * @param array $args make's arguments
	 * @param string $sudo sudo command
	 */
	public function __construct($cwd, array $args = null, $sudo = null) {
		$this->cwd = $cwd;
		$this->sudo = $sudo;
		$this->args = $args;
	}

	/**
	 *
	 * @param bool $verbose
	 * @throws \pharext\Exception
	 */
	public function run($verbose = false) {
		$pwd = getcwd();
		if (!chdir($this->cwd)) {
			throw new Exception;
		}
		try {
			$cmd = new ExecCmd("make", $verbose);
			if (isset($this->sudo)) {
				$cmd->setSu($this->sudo);
			}
			$args = $this->args;
			if (!$verbose) {
				$args = array_merge((array) $args, ["-s"]);
			}
			$cmd->run($args);
		} finally {
			chdir($pwd);
		}
	}
}
