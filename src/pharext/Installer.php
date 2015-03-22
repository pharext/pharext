<?php

namespace pharext;

use Phar;
use pharext\Cli\Args as CliArgs;
use pharext\Cli\Command as CliCommand;

/**
 * The extension install command executed by the extension phar
 */
class Installer implements Command
{
	use CliCommand;
	
	/**
	 * The temporary directory we should operate in
	 * @var string
	 */
	private $tmp;

	/**
	 * The directory we came from
	 * @var string
	 */
	private $cwd;

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
		]);
	}
	
	/**
	 * Cleanup temp directory
	 */
	public function __destruct() {
		$this->cleanup();
	}

	/**
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		$this->cwd = getcwd();
		$this->tmp = $this->tempname(basename(Phar::running(false)));

		$phar = new Phar(Phar::running(false));
		foreach ($phar as $entry) {
			if (fnmatch("*.ext.phar*", $entry->getBaseName())) {
				$temp = new Tempdir($entry->getBaseName());
				$phar->extractTo($temp, $entry->getFilename(), true);
				$phars[$temp] = new Phar($temp."/".$entry->getFilename());
			}
		}
		$phars[$this->tmp] = $phar;

		foreach ($phars as $phar) {
			if (isset($phar["pharext_install.php"])) {
				$callable = include $phar["pharext_install.php"];
				if (is_callable($callable)) {
					$recv[] = $callable($this);
				}
			}
		}
		
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
		
		if (isset($recv)) {
			foreach ($recv as $r) {
				$r($this);
			}
		}
		foreach ($phars as $temp => $phar) {
			$this->installPackage($phar, $temp);
		}
	}

	/**
	 * Prepares, configures, builds and installs the extension
	 */
	private function installPackage(Phar $phar, $temp) {
		$this->info("Installing %s ... \n", basename($phar->getAlias()));
		try {
			$phar->extractTo($temp, null, true);
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(3);
		}

		if (!chdir($temp)) {
			$this->error(null);
			exit(4);
		}
		
		$this->build();
		$this->activate();
		$this->cleanup($temp);
	}
	
	/**
	 * Phpize + trinity
	 */
	private function build() {
		try {
			// phpize
			$this->info("Runnin phpize ... ");
			$cmd = new ExecCmd($this->php("ize"), $this->args->verbose);
			$cmd->run();
			$this->info("OK\n");
				
			// configure
			$this->info("Running configure ... ");
			$args = ["--with-php-config=". $this->php("-config")];
			if ($this->args->configure) {
				$args = array_merge($args, $this->args->configure);
			}
			$cmd = new ExecCmd("./configure", $this->args->verbose);
			$cmd->run($args);
			$this->info("OK\n");
				
			// make
			$this->info("Running make ... ");
			$cmd = new ExecCmd("make", $this->args->verbose);
			if ($this->args->verbose) {
				$cmd->run(["-j3"]);
			} else {
				$cmd->run(["-j3", "-s"]);
			}
			$this->info("OK\n");
		
			// install
			$this->info("Running make install ... ");
			$cmd->setSu($this->args->sudo);
			if ($this->args->verbose) {
				$cmd->run(["install"]);
			} else {
				$cmd->run(["install", "-s"]);
			}
			$this->info("OK\n");
		
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			$this->error("%s\n", $cmd->getOutput());
		}
	}

	/**
	 * Perform any cleanups
	 */
	private function cleanup($temp = null) {
		if (!isset($temp)) {
			$temp = $this->tmp;
		}
		if (is_dir($temp)) {
			chdir($this->cwd);
			$this->info("Cleaning up %s ...\n", $temp);
			$this->rm($temp);
		}
	}

	/**
	 * Construct a command from prefix common-name and suffix
	 * @param type $suffix
	 * @return string
	 */
	private function php($suffix) {
		$cmd = $this->args["common-name"] . $suffix;
		if (isset($this->args->prefix)) {
			$cmd = $this->args->prefix . "/bin/" . $cmd;
		}
		return $cmd;
	}

	/**
	 * Activate extension in php.ini
	 */
	private function activate() {
		if ($this->args->ini) {
			$files = [realpath($this->args->ini)];
		} else {
			$files = array_filter(array_map("trim", explode(",", php_ini_scanned_files())));
			$files[] = php_ini_loaded_file();
		}

		$extension = basename(current(glob("modules/*.so")));
		$pattern = preg_quote($extension);

		foreach ($files as $index => $file) {
			$temp = new Tempfile("phpini");
			foreach (file($file) as $line) {
				if (preg_match("/^\s*extension\s*=\s*[\"']?{$pattern}[\"']?\s*(;.*)?\$/", $line)) {
					// already there
					$this->info("Extension already activated\n");
					return;
				}
				fwrite($temp->getStream(), $line);
			}
		}

		// not found, add extension line to the last process file
		if (isset($temp, $file)) {
			fprintf($temp->getStream(), "extension=%s\n", $extension);
			$temp->closeStream();

			$path = $temp->getPathname();
			$stat = stat($file);

			try {
				$this->info("Running INI owner transfer ... ");
				$ugid = sprintf("%d:%d", $stat["uid"], $stat["gid"]);
				$cmd = new ExecCmd("chown", $this->args->verbose);
				$cmd->setSu($this->args->sudo);
				$cmd->run([$ugid, $path]);
				$this->info("OK\n");
				
				$this->info("Running INI permission transfer ... ");
				$perm = decoct($stat["mode"] & 0777);
				$cmd = new ExecCmd("chmod", $this->args->verbose);
				$cmd->setSu($this->args->sudo);
				$cmd->run([$perm, $path]);
				$this->info("OK\n");
	
				$this->info("Running INI activation ... ");
				$cmd = new ExecCmd("mv", $this->args->verbose);
				$cmd->setSu($this->args->sudo);
				$cmd->run([$path, $file]);
				$this->info("OK\n");
			} catch (\Exception $e) {
				$this->error("%s\n", $e->getMessage());
				$this->error("%s\n", $cmd->getOutput());
				exit(5);
			}
		}
	}
}
