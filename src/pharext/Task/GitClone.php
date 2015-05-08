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
	 * @var string
	 */
	private $branch;

	/**
	 * @param string $source git repo location
	 */
	public function __construct($source, $branch = null) {
		$this->source = $source;
		$this->branch = $branch;
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Tempdir
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Fetching %s ...\n", $this->source);
		}
		$local = new Tempdir("gitclone");
		$cmd = new ExecCmd("git", $verbose);
		if (strlen($this->branch)) {
			$cmd->run(["clone", "--depth", 1, "--branch", $this->branch, $this->source, $local]);
		} else {
			$cmd->run(["clone", $this->source, $local]);
		}
		return $local;
	}
}
