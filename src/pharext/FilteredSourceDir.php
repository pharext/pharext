<?php

namespace pharext;

/**
 * Generic filtered source directory
 */
class FilteredSourceDir extends \FilterIterator implements SourceDir
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
	 * Exclude filters
	 * @var array
	 */
	private $filter = [".git/*", ".hg/*"];
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct(Command $cmd, $path) {
		$this->cmd = $cmd;
		$this->path = $path;
		parent::__construct(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path,
					\FilesystemIterator::KEY_AS_PATHNAME |
					\FilesystemIterator::CURRENT_AS_FILEINFO |
					\FilesystemIterator::SKIP_DOTS
				)
			)
		);
		foreach ([".gitignore", ".hgignore"] as $ignore) {
			if (file_exists("$path/$ignore")) {
				$this->filter = array_merge($this->filter, 
					array_map(function($pat) {
						$pat = trim($pat);
						if (substr($pat, -1) == '/') {
							$pat .= '*';
						}
						return $pat;
					}, file("$path/$ignore", 
						FILE_IGNORE_NEW_LINES |
						FILE_SKIP_EMPTY_LINES
					))
				);
			}
		}
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getBaseDir()
	 */
	public function getBaseDir() {
		return $this->path;
	}
	
	/**
	 * Implements FilterIterator
	 * @see FilterIterator::accept()
	 */
	public function accept() {
		$fn = $this->key();
		if (is_dir($fn)) {
			if ($this->cmd->getArgs()->verbose) {
				$this->info("Excluding %s\n", $fn);
			}
			return false;
		}
		$pl = strlen($this->path) + 1;
		$pn = substr($this->key(), $pl);
		foreach ($this->filter as $pat) {
			if (fnmatch($pat, $pn)) {
				if ($this->cmd->getArgs()->verbose) {
					$this->info("Excluding %s\n", $pn);
				}
				return false;
			}
		}
		if ($this->cmd->getArgs()->verbose) {
			$this->info("Packaging %s\n", $pn);
		}
		return true;
	}
}