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
	 * Installer hook
	 * @var string
	 */
	private $hook;
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct(Command $cmd, $path) {
		$sxe = simplexml_load_file("$path/package.xml");
		$sxe->registerXPathNamespace("pecl", $sxe->getDocNamespaces()[""]);
		
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
		
		if (($configure = $sxe->xpath("/pecl:package/pecl:extsrcrelease/pecl:configureoption"))) {
			$this->hook = tmpfile();
			ob_start(function($s) {
				fwrite($this->hook, $s);
				return null;
			});
			call_user_func(function() use ($configure) {
				include __DIR__."/../pharext_install.tpl.php";
			});
			ob_end_flush();
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
		if ($this->hook) {
			rewind($this->hook);
			yield "pharext_install.php" => $this->hook;
		}
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
