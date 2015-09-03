<?php

namespace pharext;

use Phar;
use pharext\Exception;

/**
 * The extension packaging command executed by bin/pharext
 */
class Packager implements Command
{
	use Cli\Command;
	
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
		$this->args = new Cli\Args([
			["h", "help", "Display this help",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			["v", "verbose", "More output",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["q", "quiet", "Less output",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["n", "name", "Extension name",
				Cli\Args::REQUIRED|Cli\Args::SINGLE|Cli\Args::REQARG],
			["r", "release", "Extension release version",
				Cli\Args::REQUIRED|Cli\Args::SINGLE|Cli\Args::REQARG],
			["s", "source", "Extension source directory",
				Cli\Args::REQUIRED|Cli\Args::SINGLE|Cli\Args::REQARG],
			["g", "git", "Use `git ls-tree` to determine file list",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["b", "branch", "Checkout this tag/branch",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::REQARG],
			["p", "pecl", "Use PECL package.xml to determine file list, name and release",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["d", "dest", "Destination directory",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::REQARG,
				"."],
			["z", "gzip", "Create additional PHAR compressed with gzip",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["Z", "bzip", "Create additional PHAR compressed with bzip",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["S", "sign", "Sign the PHAR with a private key",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::REQARG],
			["E", "zend", "Mark as Zend Extension",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			[null, "signature", "Show pharext signature",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			[null, "license", "Show pharext license",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			[null, "version", "Show pharext version",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
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
			/* source needs to be evaluated before Cli\Args validation, 
			 * so e.g. name and version can be overriden and Cli\Args 
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
			$task = new Task\GitClone($source, $this->args->branch);
		} else {
			/* print newline only once */
			$done = false;
			$task = new Task\StreamFetch($source, function($bytes_pct) use(&$done) {
				if (!$done) {
					$this->info(" %3d%% [%s>%s] \r",
						floor($bytes_pct*100),
						str_repeat("=", round(50*$bytes_pct)),
						str_repeat(" ", round(50*(1-$bytes_pct)))
					);
					if ($bytes_pct == 1) {
						$done = true;
						$this->info("\n");
					}
				}
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
		if (!stream_is_local($source) || ($this->args->git && isset($this->args->branch))) {
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
			$meta = array_merge(Metadata::all(), [
				"name" => $this->args->name,
				"release" => $this->args->release,
				"license" => $this->source->getLicense(),
				"type" => $this->args->zend ? "zend_extension" : "extension",
			]);
			$file = (new Task\PharBuild($this->source, __DIR__."/../pharext_installer.php", $meta))->run($this->verbosity());
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
