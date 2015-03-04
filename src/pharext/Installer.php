<?php

namespace pharext;

use Phar;

/**
 * The extension install command executed by the extension phar
 */
class Installer implements Command
{
	/**
	 * Command line arguments
	 * @var pharext\CliArgs
	 */
	private $args;
	
	/**
	 * Create the command
	 */
	public function __construct() {
		$this->args = new CliArgs([
			["h", "help", "Display help", 
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
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
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		$prog = array_shift($argv);
		foreach ($this->args->parse(--$argc, $argv) as $error) {
			$this->error("%s\n", $error);
		}
		
		if ($this->args["help"]) {
			$this->args->help($prog);
			exit;
		}
		
		foreach ($this->args->validate() as $error) {
			$this->error("%s\n", $error);
		}
		
		if (isset($error)) {
			if (!$this->args["quiet"]) {
				$this->args->help($prog);
			}
			exit(1);
		}
		
		$this->installPackage();
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
	 * Extract the phar to a temporary directory
	 */
	private function extract() {
		if (!$file = Phar::running(false)) {
			$this->error("Did your run the ext.phar?\n");
			exit(3);
		}
		$temp = sys_get_temp_dir()."/".basename($file, ".ext.phar");
		is_dir($temp) or mkdir($temp, 0750, true);
		$phar = new Phar($file);
		$phar->extractTo($temp, null, true);
		chdir($temp);
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
			if (($proc = proc_open(sprintf($this->args->sudo, $command)." 2>&1", [STDIN,STDOUT,STDERR], $pipes))) {
				$retval = proc_close($proc);
			} else {
				$retval = -1;
			}
		} elseif ($this->args->verbose) {
			passthru($command ." 2>&1", $retval);
		} else {
			exec($command ." 2>&1", $output, $retval);
		}
		if ($retval) {
			$this->error("Command %s failed with (%s)\n", $command, $retval);
			if (isset($output) && !$this->args->quiet) {
				printf("%s\n", implode("\n", $output));
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
	
	/**
	 * Prepares, configures, builds and installs the extension
	 */
	private function installPackage() {
		$this->extract();
		$this->exec("phpize", $this->php("ize"));
		$this->exec("configure", "./configure --with-php-config=". $this->php("-config") . " ". 
			implode(" ", (array) $this->args->configure));
		$this->exec("make", "make -sj3");
		$this->exec("install", "make -s install", true);
	}
}
