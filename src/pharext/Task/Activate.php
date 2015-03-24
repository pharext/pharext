<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\ExecCmd;
use pharext\Task;
use pharext\Tempfile;

/**
 * PHP INI activation
 */
class Activate implements Task
{
	/**
	 * @var string
	 */
	private $cwd;

	/**
	 * @var array
	 */
	private $inis;

	/**
	 * @var string
	 */
	private $sudo;

	/**
	 * @param string $cwd working directory
	 * @param array $inis custom INI or list of loaded/scanned INI files
	 * @param string $sudo sudo command
	 * @throws \pharext\Exception
	 */
	public function __construct($cwd, array $inis, $sudo = null) {
		$this->cwd = $cwd;
		$this->sudo = $sudo;
		if (!$this->inis = $inis) {
			throw new Exception("No PHP INIs given");
		}
	}

	/**
	 * @param bool $verbose
	 * @return boolean false, if extension was already activated
	 */
	public function run($verbose = false) {
		$extension = basename(current(glob("{$this->cwd}/modules/*.so")));
		$pattern = preg_quote($extension);

		foreach ($this->inis as $file) {
			$temp = new Tempfile("phpini");
			foreach (file($file) as $line) {
				if (preg_match("/^\s*extension\s*=\s*[\"']?{$pattern}[\"']?\s*(;.*)?\$/", $line)) {
					return false;
				}
				fwrite($temp->getStream(), $line);
			}
		}

		/* not found; append to last processed file, which is the main by default */
		fprintf($temp->getStream(), "extension=%s\n", $extension);
		$temp->closeStream();

		$path = $temp->getPathname();
		$stat = stat($file);

		// owner transfer
		$ugid = sprintf("%d:%d", $stat["uid"], $stat["gid"]);
		$cmd = new ExecCmd("chown", $verbose);
		if (isset($this->sudo)) {
			$cmd->setSu($this->sudo);
		}
		$cmd->run([$ugid, $path]);

		// permission transfer
		$perm = decoct($stat["mode"] & 0777);
		$cmd = new ExecCmd("chmod", $verbose);
		if (isset($this->sudo)) {
			$cmd->setSu($this->sudo);
		}
		$cmd->run([$perm, $path]);

		// rename
		$cmd = new ExecCmd("mv", $verbose);
		if (isset($this->sudo)) {
			$cmd->setSu($this->sudo);
		}
		$cmd->run([$path, $file]);

		return true;
	}
}
