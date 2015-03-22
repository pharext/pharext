<?php

namespace pharext;

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
		
		/* interrupt output stream */
		if ($verbose) {
			printf("\n");
		}
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
	 * @param string $output
	 * @param int $status
	 */
	private function suExec($command, &$output, &$status) {
		if (!($proc = proc_open($command, [STDIN,["pipe","w"],["pipe","w"]], $pipes))) {
			$status = -1;
			throw new \Exception("Failed to run {$command}");
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
		$status = proc_close($proc);
	}

	/**
	 * Run the command
	 * @param array $args
	 * @throws \Exception
	 */
	public function run(array $args = null) {
		$exec = escapeshellcmd($this->command);
		if ($args) {
			$exec .= " ". implode(" ", array_map("escapeshellarg", (array) $args));
		}
		
		if ($this->sudo) {
			$this->suExec(sprintf($this->sudo." 2>&1", $exec), $this->output, $this->status);
		} elseif ($this->verbose) {
			ob_start(function($s) {
				$this->output .= $s;
				return $s;
			}, 1);
			passthru($exec, $this->status);
			ob_end_flush();
		} else {
			exec($exec ." 2>&1", $output, $this->status);
			$this->output = implode("\n", $output);
		}
		
		if ($this->status) {
			throw new \Exception("Command {$this->command} failed ({$this->status})");
		}
	}
	
	/**
	 * Retrieve exit code of cmd run
	 * @return int
	 */
	public function getStatus() {
		return $status;
	}
	
	/**
	 * Retrieve output of cmd run
	 * @return string
	 */
	public function getOutput() {
		return $this->output;
	}
}
