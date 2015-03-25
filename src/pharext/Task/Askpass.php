<?php

namespace pharext\Task;

use pharext\Task;

/**
 * Ask password on console
 */
class Askpass implements Task
{
	/**
	 * @var string
	 */
	private $prompt;

	/**
	 * @param string $prompt
	 */
	public function __construct($prompt = "Password:") {
		$this->prompt = $prompt;
	}

	/**
	 * @param bool $verbose
	 * @return string
	 */
	public function run($verbose = false) {
		system("stty -echo");
		printf("%s ", $this->prompt);
		$pass = fgets(STDIN, 1024);
		printf("\n");
		system("stty echo");
		if (substr($pass, -1) == "\n") {
			$pass = substr($pass, 0, -1);
		}
		return $pass;
	}
}
