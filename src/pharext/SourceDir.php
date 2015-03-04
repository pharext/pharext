<?php

namespace pharext;

/**
 * Source directory interface
 */
interface SourceDir extends \Traversable
{
	/**
	 * Read the source directory
	 * 
	 * Note: Best practices are for others, but if you want to follow them, do
	 * not put constructors in interfaces. Keep your complaints, I warned you.
	 * 
	 * @param Command $cmd
	 * @param string $path
	 */
	public function __construct(Command $cmd, $path);
	
	/**
	 * Retrieve the base directory
	 * @return string
	 */
	public function getBaseDir();
}
