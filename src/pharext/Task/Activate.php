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
	private $type;

	/**
	 * @var string
	 */
	private $php_config;
	
	/**
	 * @var string
	 */
	private $sudo;

	/**
	 * @param string $cwd working directory
	 * @param array $inis custom INI or list of loaded/scanned INI files
	 * @param string $type extension or zend_extension
	 * @param string $prefix install prefix, e.g. /usr/local
	 * @param string $common_name PHP programs common name, e.g. php5
	 * @param string $sudo sudo command
	 * @throws \pharext\Exception
	 */
	public function __construct($cwd, array $inis, $type = "extension", $prefix = null, $common_name = "php", $sudo = null) {
		$this->cwd = $cwd;
		$this->type = $type;
		$this->sudo = $sudo;
		if (!$this->inis = $inis) {
			throw new Exception("No PHP INIs given");
		}
		$cmd = $common_name . "-config";
		if (isset($prefix)) {
			$cmd = $prefix . "/bin/" . $cmd;
		}
		$this->php_config = $cmd;
	}

	/**
	 * @param bool $verbose
	 * @return boolean false, if extension was already activated
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Running INI activation ...\n");
		}
		$extension = basename(current(glob("{$this->cwd}/modules/*.so")));

		if ($this->type === "zend_extension") {
			$pattern = preg_quote((new ExecCmd($this->php_config))->run(["--extension-dir"])->getOutput() . "/$extension", "/");
		} else {
			$pattern = preg_quote($extension, "/");
		}

		foreach ($this->inis as $file) {
			if ($verbose) {
				printf("Checking %s ...\n", $file);
			}
			if (!file_exists($file)) {
				throw new Exception(sprintf("INI file '%s' does not exist", $file));
			}
			$temp = new Tempfile("phpini");
			foreach (file($file) as $line) {
				if (preg_match("/^\s*{$this->type}\s*=\s*[\"']?{$pattern}[\"']?\s*(;.*)?\$/", $line)) {
					return false;
				}
				fwrite($temp->getStream(), $line);
			}
		}

		/* not found; append to last processed file, which is the main by default */
		if ($verbose) {
			printf("Activating in %s ...\n", $file);
		}
		fprintf($temp->getStream(), $this->type . "=%s\n", $extension);
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
		
		if ($verbose) {
			printf("Replaced %s ...\n", $file);
		}

		return true;
	}
}
