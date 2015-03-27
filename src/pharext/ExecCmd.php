<?php

namespace pharext;

/**
 * Execute system command
 */
class ExecCmd
{
	/**
	 * Sudo command, if the cmd needs escalated privileges
	 * @var string
	 */
	private $sudo;
	
	/**
	 * Executable of the cmd
	 * @var string
	 */
	private $command;
	
	/**
	 * Passthrough cmd output
	 * @var bool
	 */
	private $verbose;
	
	/**
	 * Output of cmd run
	 * @var string
	 */
	private $output;
	
	/**
	 * Return code of cmd run
	 * @var int
	 */
	private $status;

	/**
	 * @param string $command
	 * @param bool verbose
	 */
	public function __construct($command, $verbose = false) {
		$this->command = $command;
		$this->verbose = $verbose;
	}
	
	/**
	 * (Re-)set sudo command
	 * @param string $sudo
	 */
	public function setSu($sudo = false) {
		$this->sudo = $sudo;
	}
	
	/**
	 * Execute a program with escalated privileges handling interactive password prompt
	 * @param string $command
	 * @param bool $verbose
	 * @return int exit status
	 */
	private function suExec($command, $verbose = null) {
		if (!($proc = proc_open($command, [STDIN,["pipe","w"],["pipe","w"]], $pipes))) {
			$this->status = -1;
			throw new Exception("Failed to run {$command}");
		}
		
		$stdout = $pipes[1];
		$passwd = 0;
		$checks = 10;

		while (!feof($stdout)) {
			$R = [$stdout]; $W = []; $E = [];
			if (!stream_select($R, $W, $E, null)) {
				continue;
			}
			$data = fread($stdout, 0x1000);
			/* only check a few times */
			if ($passwd < $checks) {
				$passwd++;
				if (stristr($data, "password")) {
					$passwd = $checks + 1;
					printf("\n%s", $data);
					continue;
				}
			} elseif ($passwd > $checks) {
				/* new line after pw entry */
				printf("\n");
				$passwd = $checks;
			}
			
			if ($verbose === null) {
				print $this->progress($data, 0);
			} else {
				if ($verbose) {
					printf("%s\n", $data);
				}
				$this->output .= $data;
			}
		}
		if ($verbose === null) {
			$this->progress("", PHP_OUTPUT_HANDLER_FINAL);
		}
		return $this->status = proc_close($proc);
	}

	/**
	 * Output handler that displays some progress while soaking output
	 * @param string $string
	 * @param int $flags
	 * @return string
	 */
	private function progress($string, $flags) {
		static $c = 0;
		static $s = ["\\","|","/","-"];

		$this->output .= $string;

		return $flags & PHP_OUTPUT_HANDLER_FINAL
			? "   \r"
			: sprintf("  %s\r", $s[$c++ % count($s)]);
	}

	/**
	 * Run the command
	 * @param array $args
	 * @return \pharext\ExecCmd self
	 * @throws \pharext\Exception
	 */
	public function run(array $args = null) {
		$exec = escapeshellcmd($this->command);
		if ($args) {
			$exec .= " ". implode(" ", array_map("escapeshellarg", (array) $args));
		}
		
		if ($this->sudo) {
			$this->suExec(sprintf($this->sudo." 2>&1", $exec), $this->verbose);
		} elseif ($this->verbose) {
			ob_start(function($s) {
				$this->output .= $s;
				return $s;
			}, 1);
			passthru($exec, $this->status);
			ob_end_flush();
		} elseif ($this->verbose !== false /* !quiet */) {
			ob_start([$this, "progress"], 1);
			passthru($exec . " 2>&1", $this->status);
			ob_end_flush();
		} else {
			exec($exec ." 2>&1", $output, $this->status);
			$this->output = implode("\n", $output);
		}
		
		if ($this->status) {
			throw new Exception("Command {$exec} failed ({$this->status})");
		}

		return $this;
	}
	
	/**
	 * Retrieve exit code of cmd run
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}
	
	/**
	 * Retrieve output of cmd run
	 * @return string
	 */
	public function getOutput() {
		return $this->output;
	}
}
