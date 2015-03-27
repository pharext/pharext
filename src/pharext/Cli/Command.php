<?php

namespace pharext\Cli;

use pharext\Cli\Args as CliArgs;

use Phar;

if (!function_exists("array_column")) {
	function array_column(array $array, $col, $idx = null) {
		$result = [];
		foreach ($array as $el) {
			if (isset($idx)) {
				$result[$el[$idx]] = $el[$col];
			} else {
				$result[] = $el[$col];
			}
		}
		return $result;
	}
}

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
		$running = new Phar(Phar::running(false));

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
		printf("Usage:\n\n  \$ %s", $prog);
		
		$flags = [];
		$required = [];
		$optional = [];
		foreach ($this->args->getSpec() as $spec) {
			if ($spec[3] & CliArgs::REQARG) {
				if ($spec[3] & CliArgs::REQUIRED) {
					$required[] = $spec;
				} else {
					$optional[] = $spec;
				}
			} else {
				$flags[] = $spec;
			}
		}
	
		if ($flags) {
			printf(" [-%s]", implode("", array_column($flags, 0)));
		}
		foreach ($required as $req) {
			printf(" -%s <arg>", $req[0]);
		}
		if ($optional) {
			printf(" [-%s <arg>]", implode("|-", array_column($optional, 0)));
		}
		printf("\n\n");
		$spc = $this->args->getSpec();
		$max = max(array_map("strlen", array_column($spc, 1)));
		$max += $max % 8 + 2;
		foreach ($spc as $spec) {
			if (isset($spec[0])) {
				printf("    -%s|", $spec[0]);
			} else {
				printf("    ");
			}
			printf("--%s ", $spec[1]);
			if ($spec[3] & CliArgs::REQARG) {
				printf("<arg>  ");
			} elseif ($spec[3] & CliArgs::OPTARG) {
				printf("[<arg>]");
			} else {
				printf("       ");
			}
			printf("%s%s", str_repeat(" ", $max-strlen($spec[1])+3*!isset($spec[0])), $spec[2]);
			if ($spec[3] & CliArgs::REQUIRED) {
				printf(" (REQUIRED)");
			}
			if (isset($spec[4])) {
				printf(" [%s]", $spec[4]);
			}
			printf("\n");
		}
		printf("\n");
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
