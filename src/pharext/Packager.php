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
			[null, "signature", "Dump signature",
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
			["S", "sign", "Sign the *.ext.phar with a private key",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG]
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
		if ($this->args["signature"]) {
			exit($this->signature($prog));
		}

		try {
			if ($this->args["source"]) {
				if ($this->args["pecl"]) {
					$this->source = new PeclSourceDir($this, $this->args["source"]);
				} elseif ($this->args["git"]) {
					$this->source = new GitSourceDir($this, $this->args["source"]);
				} elseif (realpath($this->args["source"]."/pharext_package.php")) {
					$this->source = new PharextSourceDir($this, $this->args["source"]);
				} else {
					$this->source = new FilteredSourceDir($this, $this->args["source"]);
				}
			}
		} catch (\Exception $e) {
			$errs[] = $e->getMessage();
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
			printf("\n");
			if (!$this->args["quiet"]) {
				$this->help($prog);
			}
			exit(1);
		}
		
		$this->createPackage();
	}

	function signature($prog) {
		try {
			$sig = (new Phar(Phar::running(false)))->getSignature();
			printf("%s signature of %s\n%s", $sig["hash_type"], $prog, 
				chunk_split($sig["hash"], 64, "\n"));
			return 0;
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			return 2;
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
	
	private function askpass($prompt = "Password:") {
		system("stty -echo", $retval);
		if ($retval) {
			$this->error("Could not disable echo on the terminal\n");
		}
		printf("%s ", $prompt);
		$pass = fgets(STDIN, 1024);
		system("stty echo");
		if (substr($pass, -1) == "\n") {
			$pass = substr($pass, 0, -1);
		}
		return $pass;
	}

	/**
	 * Creates the extension phar
	 */
	private function createPackage() {
		$pkguniq = uniqid();
		$pkgtemp = $this->tempname($pkguniq, "phar");
		$pkgdesc = "{$this->args->name}-{$this->args->release}";
	
		$this->info("Creating phar %s ...%s", $pkgtemp, $this->args->verbose ? "\n" : " ");
		try {
			$package = new Phar($pkgtemp);
			
			if ($this->args->sign) {
				$this->info("\nUsing private key to sign phar ... \n");
				$privkey = new Openssl\PrivateKey(realpath($this->args->sign), $this->askpass());
				$privkey->sign($package);
			}

			$package->startBuffering();
			$package->buildFromIterator($this->source, $this->source->getBaseDir());
			$package->buildFromIterator($this->bundle());
			$package->addFile(__DIR__."/../pharext_installer.php", "pharext_installer.php");
			$package->setDefaultStub("pharext_installer.php");
			$package->setStub("#!/usr/bin/php -dphar.readonly=1\n".$package->getStub());
			$package->stopBuffering();

			if (!chmod($pkgtemp, 0777)) {
				$this->error(null);
			} elseif ($this->args->verbose) {
				$this->info("Created executable phar %s\n", $pkgtemp);
			} else {
				$this->info("OK\n");
			}
			if ($this->args->gzip) {
				$this->info("Compressing with gzip ... ");
				try {
					$package->compress(Phar::GZ)
						->setDefaultStub("pharext_installer.php");
					$this->info("OK\n");
				} catch (\Exception $e) {
					$this->error("%s\n", $e->getMessage());
				}
			}
			if ($this->args->bzip) {
				$this->info("Compressing with bzip ... ");
				try {
					$package->compress(Phar::BZ2)
						->setDefaultStub("pharext_installer.php");
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
				$this->error(null);
				exit(5);
			}
			$this->info("OK\n");
			if ($this->args->sign && isset($privkey)) {
				$keyname = $this->args->dest ."/". basename($pkgfile) . ".pubkey";
				$this->info("Public Key %s ... ", $keyname);
				try {
					$privkey->exportPublicKey($keyname);
					$this->info("OK\n");
				} catch (\Exception $e) {
					$this->error("%s", $e->getMessage());
				}
			}
		} 
	}
}
