<?php

namespace pharext;

require __DIR__."/../../autoload.php";

class CliArgsTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var CliArgs
	 */
	protected $args;
	
	/**
	 * @var array
	 */
	protected $spec;

	protected function setUp() {
		$this->spec = [
			["h", "help", "Display help", 
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG|CliArgs::HALT],
			["v", "verbose", "More output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["q", "quiet", "Less output",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::NOARG],
			["p", "prefix", "PHP installation prefix if phpize is not in \$PATH, e.g. /opt/php7",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG],
			["n", "common-name", "PHP common program name, e.g. php5 or zts-php",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::REQARG, 
				"php"],
			["c", "configure", "Additional extension configure flags, e.g. -c --with-flag",
				CliArgs::OPTIONAL|CliArgs::MULTI|CliArgs::REQARG],
			["s", "sudo", "Installation might need increased privileges",
				CliArgs::OPTIONAL|CliArgs::SINGLE|CliArgs::OPTARG,
				"sudo -S %s"]
		];
		$this->args = new CliArgs($this->spec);
	}

	public function testCompile() {
		$args = $this->args->compile($this->spec);
		$this->assertSame($args, $this->args);
		foreach ($this->spec as $arg) {
			$spec["-".$arg[0]] = $arg;
			$spec["--".$arg[1]] = $arg;
		}
		$this->assertSame($args->getCompiledSpec(), $spec);
	}

	public function testParseNothing() {
		$generator = $this->args->parse(0, []);
		$this->assertInstanceOf("Generator", $generator);
		foreach ($generator as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
	}
	
	public function testParseHalt() {
		foreach ($this->args->parse(1, ["-hv"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertTrue($this->args->help, "help");
		$this->assertNull($this->args->verbose, "verbose");
		$this->assertNull($this->args->quiet, "quiet");
		foreach ($this->args->parse(1, ["-vhq"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertTrue($this->args->help);
		$this->assertTrue($this->args->verbose);
		$this->assertNull($this->args->quiet);
	}
	
	public function testOptArg() {
		$this->assertFalse(isset($this->args->sudo));
		$this->assertSame("sudo -S %s", $this->args->sudo);
		foreach ($this->args->parse(1, ["--sudo"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertSame("sudo -S %s", $this->args->sudo);
		$this->assertNull($this->args->quiet);
		foreach ($this->args->parse(2, ["--sudo", "--quiet"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertSame("sudo -S %s", $this->args->sudo);
		$this->assertTrue($this->args->quiet);
		foreach ($this->args->parse(3, ["--sudo", "su -c '%s'", "--quiet"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertSame("su -c '%s'", $this->args->sudo);
	}
	
	public function testReqArg() {
		$this->assertNull($this->args->configure);
		foreach ($this->args->parse(1, ["-c"]) as $error) {
			$this->assertStringMatchesFormat("%s--configure%srequires%sargument", $error);
		}
		$this->assertTrue(isset($error));
	}
	
	public function testMulti() {
		foreach ($this->args->parse(4, ["-c", "--with-foo", "--configure", "--enable-bar"]) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		$this->assertSame(["--with-foo", "--enable-bar"], $this->args->configure);
	}
	
	public function testUnknown() {
		$this->assertNull($this->args->configure);
		foreach ($this->args->parse(1, ["--unknown"]) as $error) {
			$this->assertStringMatchesFormat("%SUnknown%s--unknown%S", $error);
		}
		$this->assertTrue(isset($error));
	}

	public function testValidate() {
		$this->args->compile([
			["r", "required-option", "This option is required",
				CliArgs::REQUIRED|CliArgs::NOARG]
		]);
		foreach ($this->args->parse(0, []) as $error) {
			throw new \Exception("Unexpected parse error: $error");
		}
		foreach ($this->args->validate() as $error) {
			$this->assertStringMatchesFormat("%srequired-option%srequired", $error);
		}
		$this->assertTrue(isset($error));
	}

}
