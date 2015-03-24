<?php

namespace pharext;

use pharext\Cli\Args as CliArgs;
use pharext\Cli\Command as CliCommand;

use Phar;
use SplObjectStorage;

/**
 * The extension install command executed by the extension phar
 */
class Installer implements Command
{
	use CliCommand;
	
	/**
	 * Create the command
	 */
	public function __construct() {
		$this->args = new CliArgs([
			["h", "help", "Display help", 
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			["v", "verbose", "More output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["q", "quiet", "Less output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["p", "prefix", "PHP installation prefix if phpize is not in \$PATH, e.g. /opt/php7",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG],
			["n", "common-name", "PHP common program name, e.g. php5 or zts-php",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG, 
				"php"],
			["c", "configure", "Additional extension configure flags, e.g. -c --with-flag",
				CliArgs::OPTIONAL|CliArgs::MULTI|CliArgs::REQARG],
			["s", "sudo", "Installation might need increased privileges",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::OPTARG,
				"sudo -S %s"],
			["i", "ini", "Activate in this php.ini instead of loaded default php.ini",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG],
			[null, "signature", "Dump package signature",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "license", "Show package license",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "name", "Show package name",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "release", "Show package release version",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "version", "Show pharext version",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
		]);
	}
	
	private function extract(Phar $phar) {
		$this->debug("Extracting %s ...\n", basename($phar->getPath()));
		return (new Task\Extract($phar))->run($this->args->verbose);
	}

	private function hooks(SplObjectStorage $phars) {
		$hooks = [];
		foreach ($phars as $phar) {
			if (isset($phar["pharext_install.php"])) {
				$callable = include $phar["pharext_install.php"];
				if (is_callable($callable)) {
					$hooks[] = $callable($this);
				}
			}
		}
		return $hooks;
	}

	/**
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		$list = new SplObjectStorage();
		$phar = new Phar(Phar::running(false));
		$temp = $this->extract($phar);

		foreach ($phar as $entry) {
			$dep_file = $entry->getBaseName();
			if (fnmatch("*.ext.phar*", $dep_file)) {
				$dep_phar = new Phar("$temp/$dep_file");
				$list[$dep_phar] = $this->extract($dep_phar);
			}
		}
		/* the actual ext.phar at last */
		$list[$phar] = $temp;

		/* installer hooks */
		$hook = $this->hooks($list);

		/* standard arg stuff */
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
			foreach (["signature", "name", "license", "release", "version"] as $opt) {
				if ($this->args[$opt]) {
					printf("%s\n", $this->metadata($opt));
					exit;
				}
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(2);
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

		/* post process hooks */
		foreach ($hook as $callback) {
			if (is_callable($callback)) {
				$callback($this);
			}
		}

		/* install packages */
		foreach ($list as $phar) {
			$this->info("Installing %s ...\n", basename($phar->getPath()));
			$this->install($list[$phar]);
			$this->activate($list[$phar]);
			$this->cleanup($list[$phar]);
			$this->info("Successfully installed %s!\n", basename($phar->getPath()));
		}
	}
	
	/**
	 * Phpize + trinity
	 */
	private function install($temp) {
		try {
			// phpize
			$this->info("Running phpize ...\n");
			$phpize = new Task\Phpize($temp, $this->args->prefix, $this->args->{"common-name"});
			$phpize->run($this->args->verbose);

			// configure
			$this->info("Running configure ...\n");
			$configure = new Task\Configure($temp, $this->args->configure, $this->args->prefix, $this->args{"common-name"});
			$configure->run($this->args->verbose);
				
			// make
			$this->info("Running make ...\n");
			$make = new Task\Make($temp);
			$make->run($this->args->verbose);

			// install
			$this->info("Running make install ...\n");
			$sudo = isset($this->args->sudo) ? $this->args->sudo : null;
			$install = new Task\Make($temp, ["install"], $sudo);
			$install->run($this->args->verbose);
		
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(2);
		}
	}

	private function cleanup($temp) {
		if (is_dir($temp)) {
			$this->rm($temp);
		} elseif (file_exists($temp)) {
			unlink($temp);
		}
	}

	private function activate($temp) {
		if ($this->args->ini) {
			$files = [realpath($this->args->ini)];
		} else {
			$files = array_filter(array_map("trim", explode(",", php_ini_scanned_files())));
			$files[] = php_ini_loaded_file();
		}

		$sudo = isset($this->args->sudo) ? $this->args->sudo : null;
		$type = $this->metadata("type") ?: "php";
		
		try {
			$this->info("Running INI activation ...\n");
			$activate = new Task\Activate($temp, $files, $type, $this->args->prefix, $this->args{"common-name"}, $sudo);
			if (!$activate->run($this->args->verbose)) {
				$this->info("Extension already activated ...\n");
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(3);
		}
	}
}
