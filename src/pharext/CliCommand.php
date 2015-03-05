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
	 * Output pharext vX.Y.Z header
	 */
	function header() {
		printf("pharext v%s (c) Michael Wallner <mike@php.net>\n", VERSION);
	}
	
	/**
	 * Output command line help message
	 * @param string $prog
	 */
	public function help($prog) {
		printf("\nUsage:\n\n  \$ %s", $prog);
		
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
		foreach ($this->args->getSpec() as $spec) {
			printf("    -%s|--%s %s", $spec[0], $spec[1], ($spec[3] & CliArgs::REQARG) ? "<arg>  " : (($spec[3] & CliArgs::OPTARG) ? "[<arg>]" : "       "));
			printf("%s%s%s", str_repeat(" ", 16-strlen($spec[1])), $spec[2], ($spec[3] & CliArgs::REQUIRED) ? " (REQUIRED)" : "");
			if (isset($spec[4])) {
				printf(" [%s]", $spec[4]);
			}
			printf("\n");
		}
		printf("\n");
	}
	
	
}