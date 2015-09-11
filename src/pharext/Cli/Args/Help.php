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

		list($flags, $required, $optional, $positional) = $this->listSpec();
		if ($flags) {
			$usage .= $this->dumpFlags($flags);
		}
		if ($required) {
			$usage .= $this->dumpRequired($required);
		}
		if ($optional) {
			$usage .= $this->dumpOptional($optional);
		}
		if ($positional) {
			$usage .= $this->dumpPositional($positional);
		}

		$help = $this->dumpHelp($positional);

		return $usage . "\n\n" . $help . "\n";
	}

	function listSpec() {
		$flags = [];
		$required = [];
		$optional = [];
		$positional = [];
		foreach ($this->args->getSpec() as $spec) {
			if (is_numeric($spec[0])) {
				$positional[] = $spec;
			} elseif ($spec[3] & Args::REQUIRED) {
				$required[] = $spec;
			} elseif ($spec[3] & (Args::OPTARG|Args::REQARG)) {
				$optional[] = $spec;
			} else {
				$flags[] = $spec;
			}
		}

		return [$flags, $required, $optional, $positional]
			+ compact("flags", "required", "optional", "positional");
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
		$req = array_filter($optional, function($a) {
			return $a[3] & Args::REQARG;
		});
		$opt = array_filter($optional, function($a) {
			return $a[3] & Args::OPTARG;
		});

		$dump = "";
		if ($req) {
			$dump .= sprintf(" [-%s <arg>]", implode("|-", array_column($req, 0)));
		}
		if ($opt) {
			$dump .= sprintf(" [-%s [<arg>]]", implode("|-", array_column($opt, 0)));
		}
		return $dump;
	}

	function dumpPositional(array $positional) {
		$dump = " [--]";
		$conv = [];
		foreach ($positional as $pos) {
			$conv[$pos[0]][] = $pos;
		}
		$opts = [];
		foreach ($conv as $positional) {
			$args = implode("|", array_column($positional, 1));
			if ($positional[0][3] & Args::REQUIRED) {
				$dump .= sprintf(" <%s>", $args);
			} else {
				$dump .= sprintf(" [<%s>]", $args);
			}
			if ($positional[0][3] & Args::MULTI) {
				$dump .= sprintf(" [<%s>]...", $args);
			}
			/*
			foreach ($positional as $pos) {
				if ($pos[3] & Args::REQUIRED) {
					$dump .= sprintf(" <%s>", $pos[1]);
				} else {
					$opts[] = $pos;
					//$dump .= sprintf(" [<%s>]", $pos[1]);
				}
				if ($pos[3] & Args::MULTI) {
					$dump .= sprintf(" [<%s>]...", $pos[1]);
				}
			}
			 */
		}
		return $dump;
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
			if (is_numeric($spec[0])) {
				$dump .= sprintf("--   %s ", $spec[1]);
			} elseif (isset($spec[0])) {
				$dump .= sprintf("-%s|", $spec[0]);
			}
			if (!is_numeric($spec[0])) {
				$dump .= sprintf("--%s ", $spec[1]);
			}
			if ($spec[3] & Args::REQARG) {
				$dump .= "<arg>  ";
			} elseif ($spec[3] & Args::OPTARG) {
				$dump .= "[<arg>]";
			} else {
				$dump .= "       ";
			}

			$space = str_repeat(" ", $max-strlen($spec[1])+3*!isset($spec[0]));
			$dump .= $space;
			$dump .= str_replace("\n", "\n                        $space", $spec[2]);

			if ($spec[3] & Args::REQUIRED) {
				$dump .= " (REQUIRED)";
			}
			if ($spec[3] & Args::MULTI) {
				$dump .= " (MULTIPLE)";
			}
			if (isset($spec[4])) {
				$dump .= sprintf(" [%s]", $spec[4]);
			}
			$dump .= "\n";
		}
		return $dump;
	}
}
