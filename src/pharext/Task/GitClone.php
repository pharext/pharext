<?php

namespace pharext\Task;

use pharext\ExecCmd;
use pharext\Task;
use pharext\Tempdir;

/**
 * Clone a git repo
 */
class GitClone implements Task
{
	/**
	 * @var string
	 */
	private $source;

	/**
	 * @param string $source git repo location
	 */
	public function __construct($source) {
		$this->source = $source;
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Tempdir
	 */
	public function run($verbose = false) {
		$local = new Tempdir("gitclone");
		$cmd = new ExecCmd("git", $verbose);
		$cmd->run(["clone", $this->source, $local]);
		return $local;
	}
}
