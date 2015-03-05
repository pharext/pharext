<?php

namespace pharext;

use Phar;

/**
 * The extension packaging command executed by bin/pharext
 */
class Packager implements Command
{
	use CliCommand;
	
	/**
	 * Extension source directory
	 * @var pharext\SourceDir
	 */
	private $source;
	
	/**
	 * Create the command
	 */
	public function __construct() {
		$this->args = new CliArgs([
			["h", "help", "Display this help",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			["v", "verbose", "More output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["q", "quiet", "Less output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["n", "name", "Extension name",
				CliArgs::REQUIRED|CliArgs::SINGLE|CliArgs::REQARG],
			["r", "release", "Extension release version",
				CliArgs::REQUIRED|CliArgs::SINGLE|CliArgs::REQARG],
			["s", "source", "Extension source directory",
				CliArgs::REQUIRED|CliArgs::SINGLE|CliArgs::REQARG],
			["g", "git", "Use `git ls-files` instead of the standard ignore filter",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["p", "pecl", "Use PECL package.xml instead of the standard ignore filter",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["d", "dest", "Destination directory",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG,
				"."],
			["z", "gzip", "Create additional PHAR compressed with gzip",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["Z", "bzip", "Create additional PHAR compressed with bzip",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
		]);
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		$errs = [];
		$prog = array_shift($argv);
		foreach ($this->args->parse(--$argc, $argv) as $error) {
			$errs[] = $error;
		}
		
		if ($this->args["help"]) {
			$this->header();
			$this->help($prog);
			exit;
		}
		
		if ($this->args["source"]) {
			if ($this->args["pecl"]) {
				$this->source = new PeclSourceDir($this, $this->args["source"]);
			} elseif ($this->args["git"]) {
				$this->source = new GitSourceDir($this, $this->args["source"]);
			} else {
				$this->source = new FilteredSourceDir($this, $this->args["source"]);
			}
		}
		
		foreach ($this->args->validate() as $error) {
			$errs[] = $error;
		}
		
		if ($errs) {
			if (!$this->args["quiet"]) {
				$this->header();
			}
			foreach ($errs as $err) {
				$this->error("%s\n", $err);
			}
			if (!$this->args["quiet"]) {
				$this->help($prog);
			}
			exit(1);
		}
		
		$this->createPackage();
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::getArgs()
	 */
	public function getArgs() {
		return $this->args;
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::info()
	 */
	public function info($fmt) {
		if (!$this->args->quiet) {
			vprintf($fmt, array_slice(func_get_args(), 1));
		}
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::error()
	 */
	public function error($fmt) {
		if (!$this->args->quiet) {
			vfprintf(STDERR, "ERROR: $fmt", array_slice(func_get_args(), 1));
		}
	}
	
	/**
	 * Traverses all pharext source files to bundle
	 * @return Generator
	 */
	private function bundle() {
		foreach (scandir(__DIR__) as $entry) {
			if (fnmatch("*.php", $entry)) {
				yield "pharext/$entry" => __DIR__."/$entry";
			}
		}
	}
	
	/**
	 * Creates the extension phar
	 */
	private function createPackage() {
		$pkguniq = uniqid();
		$pkgtemp = sys_get_temp_dir() ."/{$pkguniq}.phar";
		$pkgdesc = "{$this->args->name}-{$this->args->release}";
	
		$this->info("Creating phar %s ...%s", $pkgtemp, $this->args->verbose ? "\n" : " ");
		try {
			$package = new Phar($pkgtemp, 0, "ext.phar");
			$package->startBuffering();
			$package->buildFromIterator($this->source, $this->source->getBaseDir());
			$package->buildFromIterator($this->bundle());
			$package->addFile(__DIR__."/../pharext_installer.php", "pharext_installer.php");
			$package->setDefaultStub("pharext_installer.php");
			$package->setStub("#!/usr/bin/php -dphar.readonly=1\n".$package->getStub());
			$package->stopBuffering();
			
			chmod($pkgtemp, 0770);
			if ($this->args->verbose) {
				$this->info("Created executable phar %s\n", $pkgtemp);
			} else {
				$this->info("OK\n");
			}
			if ($this->args->gzip) {
				$this->info("Compressing with gzip ... ");
				try {
					$package->compress(Phar::GZ);
					$this->info("OK\n");
				} catch (\Exception $e) {
					$this->error("%s\n", $e->getMessage());
				}
			}
			if ($this->args->bzip) {
				$this->info("Compressing with bzip ... ");
				try {
					$package->compress(Phar::BZ2);
					$this->info("OK\n");
				} catch (\Exception $e) {
					$this->error("%s\n", $e->getMessage());
				}
			}
			
			unset($package);
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(4);
		}

		foreach (glob($pkgtemp."*") as $pkgtemp) {
			$pkgfile = str_replace($pkguniq, "{$pkgdesc}.ext", $pkgtemp);
			$pkgname = $this->args->dest ."/". basename($pkgfile);
			$this->info("Finalizing %s ... ", $pkgname);
			if (!rename($pkgtemp, $pkgname)) {
				$this->error("%s\n", error_get_last()["message"]);
				exit(5);
			}
			$this->info("OK\n");
		} 
	}
}
