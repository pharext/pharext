<?php

namespace pharext;

use Phar;
use PharFileInfo;
use SplFileInfo;
use pharext\Exception;

class Updater implements Command
{
	use Cli\Command;

	/**
	 * Create the command
	 */
	public function __construct() {
		$this->args = new Cli\Args([
			["h", "help", "Display this help",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			["v", "verbose", "More output",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			["q", "quiet", "Less output",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG],
			[null, "signature", "Show pharext signature",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			[null, "license", "Show pharext license",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			[null, "version", "Show pharext version",
				Cli\Args::OPTIONAL|Cli\Args::SINGLE|Cli\Args::NOARG|Cli\Args::HALT],
			[0, "path", "Path to .ext.phar to update",
				Cli\Args::REQUIRED|Cli\Args::MULTI],
		]);
	}

	/**
	 * @inheritdoc
	 * @see \pharext\Command::run()
	 */
	public function run($argc, array $argv) {
		$errs = [];
		$prog = array_shift($argv);
		foreach ($this->args->parse(--$argc, $argv) as $error) {
			$errs[] = $error;
		}

		if ($this->args["help"]) {
			$this->header();
			$this->help($prog);
			exit;
		}

		try {
			foreach (["signature", "license", "version"] as $opt) {
				if ($this->args[$opt]) {
					printf("%s\n", $this->metadata($opt));
					exit;
				}
			}
		} catch (\Exception $e) {
			$this->error("%s\n", $e->getMessage());
			exit(self::EARGS);
		}


		foreach ($this->args->validate() as $error) {
			$errs[] = $error;
		}

		if ($errs) {
			if (!$this->args["quiet"]) {
				$this->header();
			}
			foreach ($errs as $err) {
				$this->error("%s\n", $err);
			}
			printf("\n");
			if (!$this->args["quiet"]) {
				$this->help($prog);
			}
			exit(self::EARGS);
		}

		foreach ($this->args[0] as $file) {
			$info = new SplFileInfo($file);

			while ($info->isLink()) {
				$info = new SplFileInfo($info->getLinkTarget());
			}
			
			if ($info->isFile()) {
				if (!$this->updatePackage($info)) {
					$this->warn("Cannot upgrade pre-v3 packages\n");
				}
			} else {
				$this->error("File '%s' does not exist\n", $file);
				exit(self::EARGS);
			}
		}
	}

	/**
	 * Replace the pharext core in an .ext.phar package
	 * @param string $temp path to temp phar
	 * @return boolean FALSE if the package is too old (pre-v3) to upgrade
	 */
	private function replacePharext($temp) {
		$phar = new Phar($temp, Phar::CURRENT_AS_SELF);
		$phar->startBuffering();

		if (!$meta = $phar->getMetadata()) {
			// don't upgrade pre-v3 packages
			return false;
		}

		// replace current pharext files
		$core = (new Task\BundleGenerator)->run($this->verbosity());
		$phar->buildFromIterator($core);
		$stub = __DIR__."/../pharext_installer.php";
		(new Task\PharStub($phar, $stub))->run($this->verbosity());

		// check dependencies
		foreach ($phar as $info) {
			if (fnmatch("*.ext.phar*", $info->getBasename())) {
				$this->updatePackage($info, $phar);
			}
		}
		
		$phar->stopBuffering();

		$phar->setMetadata([
			"version" => Metadata::version(),
			"header" => Metadata::header(),
		] + $meta);

		$this->info("Updated pharext version from '%s' to '%s'\n",
			isset($meta["version"]) ? $meta["version"] : "(unknown)",
			$phar->getMetadata()["version"]);

		return true;
	}

	/**
	 * Update an .ext.phar package to the current pharext version
	 * @param SplFileInfo $file
	 * @param Phar $phar the parent phar containing $file as dependency
	 * @return boolean FALSE if the package is too old (pre-v3) to upgrade
	 * @throws Exception
	 */
	private function updatePackage(SplFileInfo $file, Phar $phar = null) {
		$this->info("Updating pharext core in '%s'...\n", basename($file));

		$temp = new Tempname("update", substr(strstr($file, ".ext.phar"), 4));

		if (!copy($file->getPathname(), $temp)) {
			throw new Exception;
		}
		if (!chmod($temp, $file->getPerms())) {
			throw new Exception;
		}
		
		if (!$this->replacePharext($temp)) {
			return false;
		}

		if ($phar) {
			$phar->addFile($temp, $file);
		} elseif (!rename($temp, $file->getPathname())) {
			throw new Exception;
		}

		return true;
	}
}
