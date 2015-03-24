<?php

namespace pharext\SourceDir;

use pharext\Command;
use pharext\SourceDir;

/**
 * Extension source directory which is a git repo
 */
class Git implements \IteratorAggregate, SourceDir
{
	/**
	 * The Packager command
	 * @var pharext\Command
	 */
	private $cmd;
	
	/**
	 * Base directory
	 * @var string
	 */
	private $path;
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct(Command $cmd, $path) {
		$this->cmd = $cmd;
		$this->path = $path;
	}

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getBaseDir()
	 */
	public function getBaseDir() {
		return $this->path;
	}
	
	/**
	 * Generate a list of files by `git ls-files`
	 * @return Generator
	 */
	private function generateFiles() {
		$pwd = getcwd();
		chdir($this->path);
		if (($pipe = popen("git ls-tree -r --name-only HEAD", "r"))) {
			$path = realpath($this->path);
			while (!feof($pipe)) {
				if (strlen($file = trim(fgets($pipe)))) {
					if ($this->cmd->getArgs()->verbose) {
						$this->cmd->info("Packaging %s\n", $file);
					}
					/* there may be symlinks, so no realpath here */
					if (!file_exists("$path/$file")) {
						$this->cmd->warn("File %s does not exist in %s\n", $file, $path);
					}
					yield "$path/$file";
				}
			}
			pclose($pipe);
		}
		chdir($pwd);
	}
	
	/**
	 * Implements IteratorAggregate
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {
		return $this->generateFiles();
	}
}
