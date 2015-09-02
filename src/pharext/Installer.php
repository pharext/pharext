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
	 * Cleanups
	 * @var array
	 */
	private $cleanup = [];

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
			[null, "signature", "Show package signature",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "license", "Show package license",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "name", "Show package name",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "date", "Show package release date",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			[null, "release", "Show package release version",
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

	private function extract($phar) {
		$temp = (new Task\Extract($phar))->run($this->verbosity());
		$this->cleanup[] = new Task\Cleanup($temp);
		return $temp;
	}

	private function hooks(SplObjectStorage $phars) {
		$hook = [];
		foreach ($phars as $phar) {
			if (isset($phar["pharext_package.php"])) {
				$sdir = include $phar["pharext_package.php"];
				if ($sdir instanceof SourceDir) {
					$this->args->compile($sdir->getArgs());
					$hook[] = $sdir;
				}
			}
		}
		return $hook;
	}

	private function load() {
		$list = new SplObjectStorage();
		$phar = extension_loaded("Phar")
			? new Phar(Phar::running(false))
			: new Archive(PHAREXT_PHAR);
		$temp = $this->extract($phar);

		foreach ($phar as $entry) {
			$dep_file = $entry->getBaseName();
			if (fnmatch("*.ext.phar*", $dep_file)) {
				$dep_phar = extension_loaded("Phar")
					? new Phar("$temp/$dep_file")
					: new Archive("$temp/$dep_file");
				$list[$dep_phar] = $this->extract($dep_phar);
			}
		}

		/* the actual ext.phar at last */
		$list[$phar] = $temp;
		return $list;
	}

	/**
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		try {
			/* load the phar(s) */
			$list = $this->load();
			/* installer hooks */
			$hook = $this->hooks($list);
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EEXTRACT);
		}

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
			foreach (["signature", "name", "date", "license", "release", "version"] as $opt) {
				if ($this->args[$opt]) {
					printf("%s\n", $this->metadata($opt));
					exit;
				}
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EARGS);
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
			exit(self::EARGS);
		}

		try {
			/* post process hooks */
			foreach ($hook as $sdir) {
				$sdir->setArgs($this->args);
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EARGS);
		}

		/* install packages */
		try {
			foreach ($list as $phar) {
				$this->info("Installing %s ...\n", basename($phar->getPath()));
				$this->install($list[$phar]);
				$this->activate($list[$phar]);
				$this->info("Successfully installed %s!\n", basename($phar->getPath()));
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EINSTALL);
		}
	}

	/**
	 * Phpize + trinity
	 */
	private function install($temp) {
		// phpize
		$phpize = new Task\Phpize($temp, $this->args->prefix, $this->args->{"common-name"});
		$phpize->run($this->verbosity());

		// configure
		$configure = new Task\Configure($temp, $this->args->configure, $this->args->prefix, $this->args->{"common-name"});
		$configure->run($this->verbosity());

		// make
		$make = new Task\Make($temp);
		$make->run($this->verbosity());

		// install
		$sudo = isset($this->args->sudo) ? $this->args->sudo : null;
		$install = new Task\Make($temp, ["install"], $sudo);
		$install->run($this->verbosity());
	}

	private function activate($temp) {
		if ($this->args->ini) {
			$files = [$this->args->ini];
		} else {
			$files = array_filter(array_map("trim", explode(",", php_ini_scanned_files())));
			$files[] = php_ini_loaded_file();
		}

		$sudo = isset($this->args->sudo) ? $this->args->sudo : null;
		$type = $this->metadata("type") ?: "extension";

		$activate = new Task\Activate($temp, $files, $type, $this->args->prefix, $this->args{"common-name"}, $sudo);
		if (!$activate->run($this->verbosity())) {
			$this->info("Extension already activated ...\n");
		}
	}
}
