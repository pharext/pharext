<?php

namespace pharext;

/**
 * Source directory interface, which should yield file names to package on traversal
 */
interface SourceDir extends \Traversable
{
	/**
	 * Retrieve the base directory
	 * @return string
	 */
	public function getBaseDir();

	/**
	 * Retrieve gathered package info
	 * @return array|Traversable
	 */
	public function getPackageInfo();

	/**
	 * Provide installer command line args
	 * @return array|Traversable
	 */
	public function getArgs();

	/**
	 * Process installer command line args
	 * @param \pharext\Cli\Args $args
	 */
	public function setArgs(Cli\Args $args);
}
