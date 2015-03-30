<?php

namespace pharext;

use Phar;
use pharext\Cli\Args as CliArgs;
use pharext\Cli\Command as CliCommand;
use pharext\Exception;

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
			["E", "zend", "Mark as Zend Extension",
				CliArgs::OPTIONAL|CliARgs::SINGLE|CliArgs::NOARG],
			[null, "signature", "Show pharext signature",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "license", "Show pharext license",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "version", "Show pharext version",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
		]);
	}
	
	/**
	 * Perform cleaniup
	 */
	function __destruct() {
		foreach ($this->cleanup as $cleanup) {
			$cleanup->run();
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
		try {
			foreach (["signature", "license", "version"] as $opt) {
				if ($this->args[$opt]) {
					printf("%s\n", $this->metadata($opt));
					exit;
				}
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EARGS);
		}

		try {
			/* source needs to be evaluated before CliArgs validation, 
			 * so e.g. name and version can be overriden and CliArgs 
			 * does not complain about missing arguments
			 */
			$this->loadSource();
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
			exit(self::EARGS);
		}
		
		$this->createPackage();
	}
	
	/**
	 * Download remote source
	 * @param string $source
	 * @return string local source
	 */
	private function download($source) {
		if ($this->args->git) {
			$task = new Task\GitClone($source);
		} else {
			$task = new Task\StreamFetch($source, function($bytes_pct) {
				$this->info(" %3d%% [%s>%s] \r%s",
					floor($bytes_pct*100),
					str_repeat("=", round(50*$bytes_pct)),
					str_repeat(" ", round(50*(1-$bytes_pct))),
					$bytes_pct == 1 ? "\n":""
				);
			});
		}
		$local = $task->run($this->verbosity());

		$this->cleanup[] = new Task\Cleanup($local);
		return $local;
	}

	/**
	 * Extract local archive
	 * @param stirng $source
	 * @return string extracted directory
	 */
	private function extract($source) {
		try {
			$task = new Task\Extract($source);
			$dest = $task->run($this->verbosity());
		} catch (\Exception $e) {
			if (false === strpos($e->getMessage(), "checksum mismatch")) {
				throw $e;
			}
			$dest = (new Task\PaxFixup($source))->run($this->verbosity());
		}
		
		$this->cleanup[] = new Task\Cleanup($dest);
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
			$this->cleanup[] = new Task\Cleanup($source);
		}
		$source = realpath($source);
		if (!is_dir($source)) {
			$source = $this->extract($source);
			$this->cleanup[] = new Task\Cleanup($source);
			
			if (!$this->args->git) {
				$source = (new Task\PeclFixup($source))->run($this->verbosity());
			}
		}
		return $source;
	}

	/**
	 * Load the source dir
	 * @throws \pharext\Exception
	 */
	private function loadSource(){
		if ($this->args["source"]) {
			$source = $this->localize($this->args["source"]);

			if ($this->args["pecl"]) {
				$this->source = new SourceDir\Pecl($source);
			} elseif ($this->args["git"]) {
				$this->source = new SourceDir\Git($source);
			} elseif (is_file("$source/pharext_package.php")) {
				$this->source = include "$source/pharext_package.php";
			} else {
				$this->source = new SourceDir\Basic($source);
			}

			if (!$this->source instanceof SourceDir) {
				throw new Exception("Unknown source dir $source");
			}

			foreach ($this->source->getPackageInfo() as $key => $val) {
				$this->args->$key = $val;
			}
		}
	}

	/**
	 * Creates the extension phar
	 */
	private function createPackage() {
		try {
			$meta = array_merge($this->metadata(), [
				"date" => date("Y-m-d"),
				"name" => $this->args->name,
				"release" => $this->args->release,
				"license" => @file_get_contents(current(glob($this->source->getBaseDir()."/LICENSE*"))),
				"stub" => "pharext_installer.php",
				"type" => $this->args->zend ? "zend_extension" : "extension",
			]);
			$file = (new Task\PharBuild($this->source, $meta))->run($this->verbosity());
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EBUILD);
		}

		try {
			if ($this->args->sign) {
				$this->info("Using private key to sign phar ...\n");
				$pass = (new Task\Askpass)->run($this->verbosity());
				$sign = new Task\PharSign($file, $this->args->sign, $pass);
				$pkey = $sign->run($this->verbosity());
			}

		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::ESIGN);
		}

		if ($this->args->gzip) {
			try {
				$gzip = (new Task\PharCompress($file, Phar::GZ))->run();
				$move = new Task\PharRename($gzip, $this->args->dest, $this->args->name ."-". $this->args->release);
				$name = $move->run($this->verbosity());

				$this->info("Created gzipped phar %s\n", $name);

				if ($this->args->sign) {
					$sign = new Task\PharSign($name, $this->args->sign, $pass);
					$sign->run($this->verbosity())->exportPublicKey($name.".pubkey");
				}

			} catch (\Exception $e) {
				$this->warn("%s\n", $e->getMessage());
			}
		}
		
		if ($this->args->bzip) {
			try {
				$bzip = (new Task\PharCompress($file, Phar::BZ2))->run();
				$move = new Task\PharRename($bzip, $this->args->dest, $this->args->name ."-". $this->args->release);
				$name = $move->run($this->verbosity());

				$this->info("Created bzipped phar %s\n", $name);

				if ($this->args->sign) {
					$sign = new Task\PharSign($name, $this->args->sign, $pass);
					$sign->run($this->verbosity())->exportPublicKey($name.".pubkey");
				}

			} catch (\Exception $e) {
				$this->warn("%s\n", $e->getMessage());
			}
		}

		try {
			$move = new Task\PharRename($file, $this->args->dest, $this->args->name ."-". $this->args->release);
			$name = $move->run($this->verbosity());

			$this->info("Created executable phar %s\n", $name);

			if (isset($pkey)) {
				$pkey->exportPublicKey($name.".pubkey");
			}

		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EBUILD);
		}
	}
}
