<?php

namespace pharext;

use Phar;
use PharData;
use pharext\Cli\Args as CliArgs;
use pharext\Cli\Command as CliCommand;

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
	 * Cleanups
	 * @var array
	 */
	private $cleanup = [];
	
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
			["g", "git", "Use `git ls-tree` to determine file list",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["p", "pecl", "Use PECL package.xml to determine file list, name and release",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["d", "dest", "Destination directory",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG,
				"."],
			["z", "gzip", "Create additional PHAR compressed with gzip",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["Z", "bzip", "Create additional PHAR compressed with bzip",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["S", "sign", "Sign the PHAR with a private key",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG],
			[null, "signature", "Dump signature",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
		]);
	}
	
	/**
	 * Perform cleaniup
	 */
	function __destruct() {
		foreach ($this->cleanup as $cleanup) {
			if (is_dir($cleanup)) {
				$this->rm($cleanup);
			} else {
				unlink($cleanup);
			}
		}
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
			/* source needs to be evaluated before CliArgs validation, 
			 * so e.g. name and version can be overriden and CliArgs 
			 * does not complain about missing arguments
			 */
			if ($this->args["source"]) {
				$source = $this->localize($this->args["source"]);
				if ($this->args["pecl"]) {
					$this->source = new SourceDir\Pecl($this, $source);
				} elseif ($this->args["git"]) {
					$this->source = new SourceDir\Git($this, $source);
				} else {
					$this->source = new SourceDir\Pharext($this, $source);
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
				if (!headers_sent()) {
					/* only display header, if we didn't generate any output yet */
					$this->header();
				}
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

	/**
	 * Dump program signature
	 * @param string $prog
	 * @return int exit code
	 */
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
	 * Download remote source
	 * @param string $source
	 * @return string local source
	 */
	private function download($source) {
		if ($this->args["git"]) {
			$this->info("Cloning %s ... ", $source);
			$local = new Tempdir("gitclone");
			$cmd = new ExecCmd("git", $this->args->verbose);
			$cmd->run(["clone", $source, $local]);
			if (!$this->args->verbose) {
				$this->info("OK\n");
			}
		} else {
			$this->info("Fetching remote source %s ... ", $source);
			if (!$remote = fopen($source, "r")) {
				$this->error(null);
				exit(2);
			}
			$local = new Tempfile("remote");
			if (!stream_copy_to_stream($remote, $local->getStream())) {
				$this->error(null);
				exit(2);
			}
			$local->closeStream();
			$this->info("OK\n");
		}
		
		$this->cleanup[] = $local;
		return $local->getPathname();
	}

	/**
	 * Extract local archive
	 * @param stirng $source
	 * @return string extracted directory
	 */
	private function extract($source) {
		$dest = new Tempdir("local");
		if ($this->args->verbose) {
			$this->info("Extracting to %s ... ", $dest);
		}
		$archive = new PharData($source);
		$archive->extractTo($dest);
		$this->info("OK\n");
		$this->cleanup[] = $dest;
		return $dest;
	}

	/**
	 * Localize a possibly remote source
	 * @param string $source
	 * @return string local source directory
	 */
	private function localize($source) {
		if (!stream_is_local($source)) {
			$source = $this->download($source);
		}
		if (!is_dir($source)) {
			$source = $this->extract($source);
			if ($this->args["pecl"]) {
				$this->info("Sanitizing PECL dir ... ");
				$dirs = glob("$source/*", GLOB_ONLYDIR);
				$files = array_diff(glob("$source/*"), $dirs);
				$source = current($dirs);
				foreach ($files as $file) {
					rename($file, "$source/" . basename($file));
				}
				$this->info("OK\n");
			}
		}
		return $source;
	}

	/**
	 * Traverses all pharext source files to bundle
	 * @return Generator
	 */
	private function bundle() {
		$rdi = new \RecursiveDirectoryIterator(__DIR__);
		$rii = new \RecursiveIteratorIterator($rdi);
		for ($rii->rewind(); $rii->valid(); $rii->next()) {
			yield "pharext/". $rii->getSubPathname() => $rii->key();
			
		}
	}

	/**
	 * Ask for password on the console
	 * @param string $prompt
	 * @return string password
	 */
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
			$package->buildFromIterator($this->bundle(__DIR__));
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
