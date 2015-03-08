<?php

namespace pharext;

require_once __DIR__."/Version.php";

trait CliCommand
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
	 * Output pharext vX.Y.Z header
	 */
	function header() {
		printf("pharext v%s (c) Michael Wallner <mike@php.net>\n\n", VERSION);
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
			if (!isset($fmt)) {
				$fmt = "%s\n";
				$arg = error_get_last()["message"];
			} else {
				$arg = array_slice(func_get_args(), 1);
			}
			vfprintf(STDERR, "ERROR: $fmt", $arg);
		}
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
	 * Create temporary file/directory name
	 * @param string $prefix
	 * @param string $suffix
	 */
	private function tempname($prefix, $suffix = null) {
		if (!isset($suffix)) {
			$suffix = uniqid();
		}
		return sprintf("%s/%s.%s", sys_get_temp_dir(), $prefix, $suffix);
	}

	/**
	 * Create a new temp directory
	 * @param string $prefix
	 * @return string
	 */
	private function newtemp($prefix) {
		$temp = $this->tempname($prefix);
		if (!is_dir($temp)) {
			if (!mkdir($temp, 0700, true)) {
				$this->error(null);
				exit(3);
			}
		}
		return $temp;
	}

	/**
	 * rm -r
	 * @param string $dir
	 */
	private function rm($dir) {
		foreach (scandir($dir) as $entry) {
			if ($entry === "." || $entry === "..") {
				continue;
			} elseif (is_dir("$dir/$entry")) {
				$this->rm("$dir/$entry");
			} elseif (!unlink("$dir/$entry")) {
				$this->error(null);
			}
		}
		if (!rmdir($dir)) {
			$this->error(null);
		}
	}
}
