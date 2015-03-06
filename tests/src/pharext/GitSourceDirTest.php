<?php

namespace pharext;

require_once __DIR__."/../../autoload.php";

class Cmd implements Command
{
	use CliCommand;
	function __construct() {
		$this->args = new CliArgs;
	}
	function run($argc, array $argv) {
	}
}

class GitSourceDirTest extends \PHPUnit_Framework_TestCase	
{
	/**
	 * @var GitSourceDir
	 */
	protected $source;
	
	protected function setUp() {
		$this->source = new GitSourceDir(new Cmd, ".");
	}
	
	public function testGetBaseDir() {
		$this->assertSame($this->source->getBaseDir(), ".");
	}
	
	public function testIterator() {
		$git_files = `git ls-files | xargs -I{} -n1 echo \$(pwd)/{}`;
		$dir_files = implode("\n", iterator_to_array($this->source->getIterator()))."\n";
		$this->assertSame($git_files, $dir_files);
	}
}
