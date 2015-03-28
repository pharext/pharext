<?php

namespace pharext\SourceDir;

use pharext\Cli\Args;
use pharext\Exception;
use pharext\SourceDir;
use pharext\Tempfile;

/**
 * A PECL extension source directory containing a v2 package.xml
 */
class Pecl implements \IteratorAggregate, SourceDir
{
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
	 * The package.xml
	 * @var string
	 */
	private $file;
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::__construct()
	 */
	public function __construct($path) {
		if (is_file("$path/package2.xml")) {
			$sxe = simplexml_load_file($this->file = "$path/package2.xml");
		} elseif (is_file("$path/package.xml")) {
			$sxe = simplexml_load_file($this->file = "$path/package.xml");
		} else {
			throw new Exception("Missing package.xml in $path");
		}
		
		$sxe->registerXPathNamespace("pecl", $sxe->getDocNamespaces()[""]);
		
		$this->sxe = $sxe;
		$this->path = realpath($path);
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getBaseDir()
	 */
	public function getBaseDir() {
		return $this->path;
	}

	/**
	 * Retrieve gathered package info
	 * @return Generator
	 */
	public function getPackageInfo() {
		if (($name = $this->sxe->xpath("/pecl:package/pecl:name"))) {
			yield "name" => (string) $name[0];
		}
		if (($release = $this->sxe->xpath("/pecl:package/pecl:version/pecl:release"))) {
			yield "release" => (string) $release[0];
		}
		if ($this->sxe->xpath("/pecl:package/pecl:zendextsrcrelease")) {
			yield "zend" => true;
		}
	}

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::getArgs()
	 */
	public function getArgs() {
		$configure = $this->sxe->xpath("/pecl:package/pecl:extsrcrelease/pecl:configureoption");
		foreach ($configure as $cfg) {
			yield [null, $cfg["name"], ucfirst($cfg["prompt"]), Args::OPTARG,
				strlen($cfg["default"]) ? $cfg["default"] : null];
		}
		$configure = $this->sxe->xpath("/pecl:package/pecl:zendextsrcrelease/pecl:configureoption");
		foreach ($configure as $cfg) {
			yield [null, $cfg["name"], ucfirst($cfg["prompt"]), Args::OPTARG,
				strlen($cfg["default"]) ? $cfg["default"] : null];
		}
	}

	/**
	 * @inheritdoc
	 * @see \pharext\SourceDir::setArgs()
	 */
	public function setArgs(Args $args) {
		$configure = $this->sxe->xpath("/pecl:package/pecl:extsrcrelease/pecl:configureoption");
		foreach ($configure as $cfg) {
			if (isset($args[$cfg["name"]])) {
				$args->configure = "--{$cfg["name"]}={$args[$cfg["name"]]}";
			}
		}
		$configure = $this->sxe->xpath("/pecl:package/pecl:zendextsrcrelease/pecl:configureoption");
		foreach ($configure as $cfg) {
			if (isset($args[$cfg["name"]])) {
				$args->configure = "--{$cfg["name"]}={$args[$cfg["name"]]}";
			}
		}
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
		/* hook  */
		$temp = tmpfile();
		fprintf($temp, "<?php\nreturn new %s(__DIR__);\n", get_class($this));
		rewind($temp);
		yield "pharext_package.php" => $temp;

		/* deps */
		$dependencies = $this->sxe->xpath("/pecl:package/pecl:dependencies/pecl:required/pecl:package");
		foreach ($dependencies as $key => $dep) {
			if (($glob = glob("{$this->path}/{$dep->name}-*.ext.phar*"))) {
				usort($glob, function($a, $b) {
					return version_compare(
						substr($a, strpos(".ext.phar", $a)),
						substr($b, strpos(".ext.phar", $b))
					);
				});
				yield end($glob);
			}
		}

		/* files */
		yield realpath($this->file);
		foreach ($this->sxe->xpath("//pecl:file") as $file) {
			yield realpath($this->path ."/". $this->dirOf($file) ."/". $file["name"]);
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
