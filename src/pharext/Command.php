<?php

namespace pharext;

/**
 * Command interface
 */
interface Command
{
	/**
	 * Argument error
	 */
	const EARGS = 1;
	/**
	 * Build error
	 */
	const EBUILD = 2;
	/**
	 * Signature error
	 */
	const ESIGN = 3;
	/**
	 * Extract/unpack error
	 */
	const EEXTRACT = 4;
	/**
	 * Install error
	 */
	const EINSTALL = 5;
	
	/**
	 * Retrieve command line arguments
	 * @return pharext\Cli\Args
	 */
	public function getArgs();
	
	/**
	 * Print debug message
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function debug($fmt);
	
	/**
	 * Print info
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function info($fmt);
	
	/**
	 * Print warning
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function warn($fmt);

	/**
	 * Print error
	 * @param string $fmt
	 * @param string ...$args
	 */
	public function error($fmt);

	/**
	 * Execute the command
	 * @param int $argc command line argument count
	 * @param array $argv command line argument list
	 */
	public function run($argc, array $argv);
}
