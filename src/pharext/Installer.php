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
				"sudo -S %s"]
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
				$temp = $this->newtemp($entry->getBaseName());
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

		$this->exec("phpize", $this->php("ize"));
		$this->exec("configure", "./configure --with-php-config=". $this->php("-config") . " ". 
			implode(" ", (array) $this->args->configure));
		$this->exec("make", $this->args->verbose ? "make -j3" : "make -sj3");
		$this->exec("install", $this->args->verbose ? "make install" : "make -s install", true);

		$this->cleanup($temp);

		$this->info("Don't forget to activiate the extension in your php.ini!\n\n");
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
	 * Execute a program with escalated privileges handling interactive password prompt
	 * @param string $command
	 * @param string $output
	 * @return int
	 */
	private function sudo($command, &$output) {
		if (!($proc = proc_open($command, [STDIN,["pipe","w"],["pipe","w"]], $pipes))) {
			return -1;
		}
		$stdout = $pipes[1];
		$passwd = 0;
		while (!feof($stdout)) {
			$R = [$stdout]; $W = []; $E = [];
			if (!stream_select($R, $W, $E, null)) {
				continue;
			}
			$data = fread($stdout, 0x1000);
			/* only check a few times */
			if ($passwd++ < 10) {
				if (stristr($data, "password")) {
					printf("\n%s", $data);
				}
			}
			$output .= $data;
		}
		return proc_close($proc);
	}
	/**
	 * Execute a system command
	 * @param string $name pretty name
	 * @param string $command full command
	 * @param bool $sudo whether the command may need escalated privileges
	 */
	private function exec($name, $command, $sudo = false) {
		$this->info("Running %s ...%s", $this->args->verbose ? $command : $name, $this->args->verbose ? "\n" : " ");
		if ($sudo && isset($this->args->sudo)) {
			$retval = $this->sudo(sprintf($this->args->sudo." 2>&1", $command), $output);
		} elseif ($this->args->verbose) {
			passthru($command ." 2>&1", $retval);
		} else {
			exec($command ." 2>&1", $output, $retval);
			$output = implode("\n", $output);
		}
		if ($retval) {
			$this->error("Command %s failed with (%s)\n", $command, $retval);
			if (isset($output) && !$this->args->quiet) {
				printf("%s\n", $output);
			}
			exit(2);
		}
		$this->info("OK\n");
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
}
