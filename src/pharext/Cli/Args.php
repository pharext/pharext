<?php

namespace pharext\Cli;

/**
 * Command line arguments
 */
class Args implements \ArrayAccess
{
	/**
	 * Optional option
	 */
	const OPTIONAL = 0x000;

	/**
	 * Required Option
	 */
	const REQUIRED = 0x001;

	/**
	 * Only one value, even when used multiple times
	 */
	const SINGLE = 0x000;

	/**
	 * Aggregate an array, when used multiple times
	 */
	const MULTI = 0x010;

	/**
	 * Option takes no argument
	 */
	const NOARG = 0x000;

	/**
	 * Option requires an argument
	 */
	const REQARG = 0x100;

	/**
	 * Option takes an optional argument
	 */
	const OPTARG = 0x200;

	/**
	 * Option halts processing
	 */
	const HALT = 0x10000000;

	/**
	 * Original option spec
	 * @var array
	 */
	private $orig = [];

	/**
	 * Compiled spec
	 * @var array
	 */
	private $spec = [];

	/**
	 * Parsed args
	 * @var array
	 */
	private $args = [];

	/**
	 * Compile the original spec
	 * @param array|Traversable $spec
	 */
	public function __construct($spec = null) {
		if (is_array($spec) || $spec instanceof Traversable) {
			$this->compile($spec);
		}

	}

	/**
	 * Compile the original spec
	 * @param array|Traversable $spec
	 * @return pharext\CliArgs self
	 */
	public function compile($spec) {
		foreach ($spec as $arg) {
			if (isset($arg[0]) && is_numeric($arg[0])) {
				$arg[3] &= ~0xf00;
				$this->spec["--".$arg[0]] = $arg;
			} elseif (isset($arg[0])) {
				$this->spec["-".$arg[0]] = $arg;
				$this->spec["--".$arg[1]] = $arg;
			} else {
				$this->spec["--".$arg[1]] = $arg;
			}
			$this->orig[] = $arg;
		}
		return $this;
	}

	/**
	 * Get original spec
	 * @return array
	 */
	public function getSpec() {
		return $this->orig;
	}

	/**
	 * Get compiled spec
	 * @return array
	 */
	public function getCompiledSpec() {
		return $this->spec;
	}

	/**
	 * Parse command line arguments according to the compiled spec.
	 *
	 * The Generator yields any parsing errors.
	 * Parsing will stop when all arguments are processed or the first option
	 * flagged CliArgs::HALT was encountered.
	 *
	 * @param int $argc
	 * @param array $argv
	 * @return Generator
	 */
	public function parse($argc, array $argv) {
		for ($f = false, $p = 0, $i = 0; $i < $argc; ++$i) {
			$o = $argv[$i];

			if ($o{0} === "-" && strlen($o) > 2 && $o{1} !== "-") {
				// multiple short opts, e.g. -vps
				$argc += strlen($o) - 2;
				array_splice($argv, $i, 1, array_map(function($s) {
					return "-$s";
				}, str_split(substr($o, 1))));
				$o = $argv[$i];
			} elseif ($o{0} === "-" && strlen($o) > 2 && $o{1} === "-" && 0 < ($eq = strpos($o, "="))) {
				// long opt with argument, e.g. --foo=bar
				$argc++;
				array_splice($argv, $i, 1, [
					substr($o, 0, $eq++),
					substr($o, $eq)
				]);
				$o = $argv[$i];
			} elseif ($o === "--") {
				// only positional args following
				$f = true;
				continue;
			}

			if ($f || !isset($this->spec[$o])) {
				if ($o{0} !== "-" && isset($this->spec["--$p"])) {
					$this[$p] = $o;
					if (!$this->optIsMulti($p)) {
						++$p;
					}
				} else {
					yield sprintf("Unknown option %s", $o);
				}
			} elseif (!$this->optAcceptsArg($o)) {
				$this[$o] = true;
			} elseif ($i+1 < $argc && !isset($this->spec[$argv[$i+1]])) {
				$this[$o] = $argv[++$i];
			} elseif ($this->optRequiresArg($o)) {
				yield sprintf("Option --%s requires an argument", $this->optLongName($o));
			} else {
				// OPTARG
				$this[$o] = $this->optDefaultArg($o);
			}

			if ($this->optHalts($o)) {
				return;
			}
		}
	}

	/**
	 * Validate that all required options were given.
	 *
	 * The Generator yields any validation errors.
	 *
	 * @return Generator
	 */
	public function validate() {
		$required = array_filter($this->orig, function($spec) {
			return $spec[3] & self::REQUIRED;
		});
		foreach ($required as $req) {
			if ($req[3] & self::MULTI) {
				if (is_array($this[$req[0]])) {
					continue;
				}
			} elseif (strlen($this[$req[0]])) {
				continue;
			}
			if (is_numeric($req[0])) {
				yield sprintf("Argument <%s> is required", $req[1]);
			} else {
				yield sprintf("Option --%s is required", $req[1]);
			}
		}
	}


	public function toArray() {
		$args = [];
		foreach ($this->spec as $spec) {
			$opt = $this->opt($spec[1]);
			$args[$opt] = $this[$opt];
		}
		return $args;
	}

	/**
	 * Retreive the default argument of an option
	 * @param string $o
	 * @return mixed
	 */
	private function optDefaultArg($o) {
		$o = $this->opt($o);
		if (isset($this->spec[$o][4])) {
			return $this->spec[$o][4];
		}
		return null;
	}

	/**
	 * Retrieve the help message of an option
	 * @param string $o
	 * @return string
	 */
	private function optHelp($o) {
		$o = $this->opt($o);
		if (isset($this->spec[$o][2])) {
			return $this->spec[$o][2];
		}
		return "";
	}

	/**
	 * Retrieve option's flags
	 * @param string $o
	 * @return int
	 */
	private function optFlags($o) {
		$o = $this->opt($o);
		if (isset($this->spec[$o])) {
			return $this->spec[$o][3];
		}
		return null;
	}

	/**
	 * Check whether an option is flagged for halting argument processing
	 * @param string $o
	 * @return boolean
	 */
	private function optHalts($o) {
		return $this->optFlags($o) & self::HALT;
	}

	/**
	 * Check whether an option needs an argument
	 * @param string $o
	 * @return boolean
	 */
	private function optRequiresArg($o) {
		return $this->optFlags($o) & self::REQARG;
	}

	/**
	 * Check wether an option accepts any argument
	 * @param string $o
	 * @return boolean
	 */
	private function optAcceptsArg($o) {
		return $this->optFlags($o) & 0xf00;
	}

	/**
	 * Check whether an option can be used more than once
	 * @param string $o
	 * @return boolean
	 */
	private function optIsMulti($o) {
		return $this->optFlags($o) & self::MULTI;
	}

	/**
	 * Retreive the long name of an option
	 * @param string $o
	 * @return string
	 */
	private function optLongName($o) {
		$o = $this->opt($o);
		return is_numeric($this->spec[$o][0]) ? $this->spec[$o][0] : $this->spec[$o][1];
	}

	/**
	 * Retreive the short name of an option
	 * @param string $o
	 * @return string
	 */
	private function optShortName($o) {
		$o = $this->opt($o);
		return is_numeric($this->spec[$o][0]) ? null : $this->spec[$o][0];
	}

	/**
	 * Retreive the canonical name (--long-name) of an option
	 * @param string $o
	 * @return string
	 */
	private function opt($o) {
		if (is_numeric($o)) {
			return "--$o";
		}
		if ($o{0} !== '-') {
			if (strlen($o) > 1) {
				$o = "-$o";
			}
			$o = "-$o";
		}
		return $o;
	}

	/**@+
	 * Implements ArrayAccess and virtual properties
	 */
	function offsetExists($o) {
		$o = $this->opt($o);
		return isset($this->args[$o]);
	}
	function __isset($o) {
		return $this->offsetExists($o);
	}
	function offsetGet($o) {
		$o = $this->opt($o);
		if (isset($this->args[$o])) {
			return $this->args[$o];
		}
		return $this->optDefaultArg($o);
	}
	function __get($o) {
		return $this->offsetGet($o);
	}
	function offsetSet($o, $v) {
		$osn = $this->optShortName($o);
		$oln = $this->optLongName($o);
		if ($this->optIsMulti($o)) {
			if (isset($osn)) {
				$this->args["-$osn"][] = $v;
			}
			$this->args["--$oln"][] = $v;
		} else {
			if (isset($osn)) {
				$this->args["-$osn"] = $v;
			}
			$this->args["--$oln"] = $v;
		}
	}
	function __set($o, $v) {
		$this->offsetSet($o, $v);
	}
	function offsetUnset($o) {
		unset($this->args["-".$this->optShortName($o)]);
		unset($this->args["--".$this->optLongName($o)]);
	}
	function __unset($o) {
		$this->offsetUnset($o);
	}
	/**@-*/
}
