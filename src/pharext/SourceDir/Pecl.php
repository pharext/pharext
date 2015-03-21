<?php

namespace pharext\SourceDir;

use pharext\Command;
use pharext\SourceDir;

/**
 * A PECL extension source directory containing a v2 package.xml
 */
class Pecl implements \IteratorAggregate, SourceDir
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
		if (realpath("$path/package2.xml")) {
			$sxe = simplexml_load_file("$path/package2.xml");
		} elseif (realpath("$path/package.xml")) {
			$sxe = simplexml_load_file("$path/package.xml");
		} else {
			throw new \Exception("Missing package.xml in $path");
		}
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
	 * Render installer hook
	 * @param array $configure
	 * @return string
	 */
	private static function loadHook($configure, $dependencies) {
		return include __DIR__."/../../pharext_install.tpl.php";
	}

	/**
	 * Create installer hook
	 * @return \Generator
	 */
	private function generateHooks() {
		$dependencies = $this->sxe->xpath("/pecl:package/pecl:dependencies/pecl:required/pecl:package");
		foreach ($dependencies as $key => $dep) {
			if (($glob = glob("{$this->path}/{$dep->name}-*.ext.phar*"))) {
				usort($glob, function($a, $b) {
					return version_compare(
						substr($a, strpos(".ext.phar", $a)),
						substr($b, strpos(".ext.phar", $b))
					);
				});
				yield realpath($this->path."/".end($glob));
			} else {
				unset($dependencies[$key]);
			}
		}
		$configure = $this->sxe->xpath("/pecl:package/pecl:extsrcrelease/pecl:configureoption");
		if ($configure) {
			$fd = tmpfile();
			ob_start(function($s) use($fd){
				fwrite($fd, $s);
				return null;
			});
			self::loadHook($configure, $dependencies);
			ob_end_flush();
			rewind($fd);
			yield "pharext_install.php" => $fd;
		}
	}
	
	/**
	 * Generate a list of files from the package.xml
	 * @return Generator
	 */
	private function generateFiles() {
		foreach ($this->generateHooks() as $file => $hook) {
			if ($this->cmd->getArgs()->verbose) {
				$this->cmd->info("Packaging %s\n", is_string($hook) ? $hook : $file);
			}
			yield $file => $hook;
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
