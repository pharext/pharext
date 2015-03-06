<?php

namespace pharext;

require __DIR__."/../../autoload.php";

class Cmd2 implements Command
{
	use CliCommand;
	function __construct() {
		$this->args = new CliArgs;
	}
	function run($argc, array $argv) {
	}
}

class FilteredSourceDirTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var FilteredSourceDir
	 */
	protected $source;
	
	protected function setUp() {
		$this->source = new FilteredSourceDir(new Cmd2, ".");
	}
	
	public function testIterator() {
		$gitdir = new GitSourceDir(new Cmd2, ".");
		$gitfiles = iterator_to_array($gitdir);
		sort($gitfiles);
		
		$filtered = array_values(iterator_to_array($this->source));
		$fltfiles = array_map(function($fi) {
			return $fi->getRealpath();
		}, $filtered);
		sort($fltfiles);
		
		$this->assertEquals($gitfiles, $fltfiles);
	}
}