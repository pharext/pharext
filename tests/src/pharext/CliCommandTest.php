<?php

namespace pharext;

class CliCommandTest extends \PHPUnit_Framework_TestCase
{
	use CliCommand;
	
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
	
	public function testHelp() {
		$this->expectOutputString(<<<EOF

Usage:

  $ testprog [-hvqs] [-p|-n|-c <arg>]

    -h|--help                    Display help
    -v|--verbose                 More output
    -q|--quiet                   Less output
    -p|--prefix <arg>            PHP installation prefix if phpize is not in \$PATH, e.g. /opt/php7
    -n|--common-name <arg>       PHP common program name, e.g. php5 or zts-php [php]
    -c|--configure <arg>         Additional extension configure flags, e.g. -c --with-flag
    -s|--sudo [<arg>]            Installation might need increased privileges [sudo -S %s]


EOF
		);
		$this->help("testprog");
	}
}
