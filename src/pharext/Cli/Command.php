<?php

namespace pharext\Cli;

use pharext\Archive;
use pharext\Cli\Args as CliArgs;

use Phar;

trait Command
{
	/**
	 * Command line arguments
	 * @var pharext\CliArgs
	 */
	private $args;
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::getArgs()
	 */
	public function getArgs() {
		return $this->args;
	}

	/**
	 * Retrieve metadata of the currently running phar
	 * @param string $key
	 * @return mixed
	 */
	public function metadata($key = null) {
		if (extension_loaded("Phar")) {
			$running = new Phar(Phar::running(false));
		} else {
			$running = new Archive(PHAREXT_PHAR);
		}

		if ($key === "signature") {
			$sig = $running->getSignature();
			return sprintf("%s signature of %s\n%s", 
				$sig["hash_type"],
				$this->metadata("name"),
				chunk_split($sig["hash"], 64, "\n"));
		}

		$metadata = $running->getMetadata();
		if (isset($key)) {
			return $metadata[$key];
		}
		return $metadata;
	}

	/**
	 * Output pharext vX.Y.Z header
	 */
	public function header() {
		if (!headers_sent()) {
			/* only display header, if we didn't generate any output yet */
			printf("%s\n\n", $this->metadata("header"));
		}
	}
	
	/**
	 * @inheritdoc
	 * @see \pharext\Command::debug()
	 */
	public function debug($fmt) {
		if ($this->args->verbose) {
			vprintf($fmt, array_slice(func_get_args(), 1));
		}
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
	 * @see \pharext\Command::warn()
	 */
	public function warn($fmt) {
		if (!$this->args->quiet) {
			if (!isset($fmt)) {
				$fmt = "%s\n";
				$arg = error_get_last()["message"];
			} else {
				$arg = array_slice(func_get_args(), 1);
			}
			vfprintf(STDERR, "Warning: $fmt", $arg);
		}
	}

	/**
	 * @inheritdoc
	 * @see \pharext\Command::error()
	 */
	public function error($fmt) {
		if (!isset($fmt)) {
			$fmt = "%s\n";
			$arg = error_get_last()["message"];
		} else {
			$arg = array_slice(func_get_args(), 1);
		}
		vfprintf(STDERR, "ERROR: $fmt", $arg);
	}

	/**
	 * Output command line help message
	 * @param string $prog
	 */
	public function help($prog) {
		print new Args\Help($prog, $this->args);
	}
	
	/**
	 * Verbosity
	 * @return boolean
	 */
	public function verbosity() {
		if ($this->args->verbose) {
			return true;
		} elseif ($this->args->quiet) {
			return false;
		} else {
			return null;
		}
	}
}
