<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\SourceDir;
use pharext\Task;
use pharext\Tempname;

use Phar;

/**
 * Build phar
 */
class PharBuild implements Task
{
	/**
	 * @var \pharext\SourceDir
	 */
	private $source;

	/**
	 * @var string
	 */
	private $stub;

	/**
	 * @var array
	 */
	private $meta;

	/**
	 * @var bool
	 */
	private $readonly;

	/**
	 * @param SourceDir $source extension source directory
	 * @param string $stub path to phar stub
	 * @param array $meta phar meta data
	 * @param bool $readonly whether the stub has -dphar.readonly=1 set
	 */
	public function __construct(SourceDir $source = null, $stub, array $meta = null, $readonly = true) {
		$this->source = $source;
		$this->stub = $stub;
		$this->meta = $meta;
		$this->readonly = $readonly;
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Tempname
	 * @throws \pharext\Exception
	 */
	public function run($verbose = false) {
		/* Phar::compress() and ::convert*() use strtok("."), ugh!
		 * so, be sure to not use any other dots in the filename
		 * except for .phar
		 */
		$temp = new Tempname("", "-pharext.phar");

		$phar = new Phar($temp);
		$phar->startBuffering();

		if ($this->meta) {
			$phar->setMetadata($this->meta);
		}
		if ($this->stub) {
			(new PharStub($phar, $this->stub))->run($verbose);
		}

		$phar->buildFromIterator((new Task\BundleGenerator)->run($verbose));

		if ($this->source) {
			$bdir = $this->source->getBaseDir();
			$blen = strlen($bdir);
			foreach ($this->source as $index => $file) {
				if (is_resource($file)) {
					$mode = fstat($file)["mode"] & 07777;
					$phar[$index] = $file;
				} else {
					$mode = stat($file)["mode"] & 07777;
					$index = trim(substr($file, $blen), "/");
					$phar->addFile($file, $index);
				}
				if ($verbose) {
					printf("Packaging %04o %s ...\n", $mode, $index);
				}
				$phar[$index]->chmod($mode);
			}
		}

		$phar->stopBuffering();

		if (!chmod($temp, fileperms($temp) | 0111)) {
			throw new Exception;
		}

		return $temp;
	}
}
