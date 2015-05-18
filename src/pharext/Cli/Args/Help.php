<?php

namespace pharext\Cli\Args;

use pharext\Cli\Args;

class Help
{
	private $args;

	function __construct($prog, Args $args) {
		$this->prog = $prog;
		$this->args = $args;
	}

	function __toString() {
		$usage = "Usage:\n\n  \$ ";
		$usage .= $this->prog;

		list($flags, $required, $optional) = $this->listSpec();
		if ($flags) {
			$usage .= $this->dumpFlags($flags);
		}
		if ($required) {
			$usage .= $this->dumpRequired($required);
		}
		if ($optional) {
			$usage .= $this->dumpOptional($optional);
		}

		$help = $this->dumpHelp();
		
		return $usage . "\n\n" . $help . "\n";
	}

	function listSpec() {
		$flags = [];
		$required = [];
		$optional = [];
		foreach ($this->args->getSpec() as $spec) {
			if ($spec[3] & Args::REQARG) {
				if ($spec[3] & Args::REQUIRED) {
					$required[] = $spec;
				} else {
					$optional[] = $spec;
				}
			} else {
				$flags[] = $spec;
			}
		}

		return [$flags, $required, $optional] + compact("flags", "required", "optional");
	}

	function dumpFlags(array $flags) {
		return sprintf(" [-%s]", implode("", array_column($flags, 0)));
	}

	function dumpRequired(array $required) {
		$dump = "";
		foreach ($required as $req) {
			$dump .= sprintf(" -%s <%s>", $req[0], $req[1]);
		}
		return $dump;
	}

	function dumpOptional(array $optional) {
		return sprintf(" [-%s <arg>]", implode("|-", array_column($optional, 0)));
	}

	function calcMaxLen() {
		$spc = $this->args->getSpec();
		$max = max(array_map("strlen", array_column($spc, 1)));
		$max += $max % 8 + 2;
		return $max;
	}

	function dumpHelp() {
		$max = $this->calcMaxLen();
		$dump = "";
		foreach ($this->args->getSpec() as $spec) {
			$dump .= "    ";
			if (isset($spec[0])) {
				$dump .= sprintf("-%s|", $spec[0]);
			}
			$dump .= sprintf("--%s ", $spec[1]);
			if ($spec[3] & Args::REQARG) {
				$dump .= "<arg>  ";
			} elseif ($spec[3] & Args::OPTARG) {
				$dump .= "[<arg>]";
			} else {
				$dump .= "       ";
			}

			$dump .= str_repeat(" ", $max-strlen($spec[1])+3*!isset($spec[0]));
			$dump .= $spec[2];

			if ($spec[3] & Args::REQUIRED) {
				$dump .= " (REQUIRED)";
			}
			if (isset($spec[4])) {
				$dump .= sprintf(" [%s]", $spec[4]);
			}
			$dump .= "\n";
		}
		return $dump;
	}
}
