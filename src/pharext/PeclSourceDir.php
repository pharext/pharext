<?php

namespace pharext;

/**
 * A PECL extension source directory containing a v2 package.xml
 */
class PeclSourceDir implements \IteratorAggregate, SourceDir
{
	/**
	 * The Packager command
	 * @var pharext\Packager
	 */
	private $cmd;
	
	/**
	 * The package.xml
	 * @var SimpleXmlElement
	 */
	private $sxe;
	
	/**
	 * The base directory
	 * @var string
	 */
	private $path;
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct(Command $cmd, $path) {
		$sxe = simplexml_load_file("$path/package.xml", null, 0, "http://pear.php.net/dtd/package-2.0");
		$sxe->registerXPathNamespace("pecl", "http://pear.php.net/dtd/package-2.0");
		
		$args = $cmd->getArgs();
		if (!isset($args->name)) {
			$name = (string) $sxe->xpath("/pecl:package/pecl:name")[0];
			foreach ($args->parse(2, ["--name", $name]) as $error) {
				$cmd->error("%s\n", $error);
			}
		}
		
		if (!isset($args->release)) {
			$release = (string) $sxe->xpath("/pecl:package/pecl:version/pecl:release")[0];
			foreach ($args->parse(2, ["--release", $release]) as $error) {
				$cmd->error("%s\n", $error);
			}
		}
		
		$this->cmd = $cmd;
		$this->sxe = $sxe;
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
	 * Compute the path of a file by parent dir nodes
	 * @param \SimpleXMLElement $ele
	 * @return string
	 */
	private function dirOf($ele) {
		$path = "";
		while (($ele = current($ele->xpath(".."))) && $ele->getName() == "dir") {
			$path = trim($ele["name"], "/") ."/". $path ;
		}
		return trim($path, "/");
	}
	
	/**
	 * Generate a list of files from the package.xml
	 * @return Generator
	 */
	private function generateFiles() {
		foreach ($this->sxe->xpath("//pecl:file") as $file) {
			$path = $this->path ."/". $this->dirOf($file) ."/". $file["name"];
			if ($this->cmd->getArgs()->verbose) {
				$this->cmd->info("Packaging %s\n", $path);
			}
			if (!($realpath = realpath($path))) {
				$this->cmd->error("File %s does not exist", $path);
			}
			yield $realpath;
		}
	}
	
	/**
	 * Implements IteratorAggregate
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {
		return $this->generateFiles();
	}
}
