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
	 * @var array
	 */
	private $meta;

	/**
	 * @var bool
	 */
	private $readonly;

	/**
	 * @param SourceDir $source extension source directory
	 * @param array $meta phar meta data
	 * @param bool $readonly whether the stub has -dphar.readonly=1 set
	 */
	public function __construct(SourceDir $source = null, array $meta = null, $readonly = true) {
		$this->source = $source;
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
			if (isset($this->meta["stub"])) {
				$phar->setDefaultStub($this->meta["stub"]);
				$phar->setStub("#!/usr/bin/php -dphar.readonly=" .
					intval($this->readonly) ."\n".
					$phar->getStub());
			}
		}

		$phar->buildFromIterator((new Task\BundleGenerator)->run());

		if ($this->source) {
			$phar->buildFromIterator($this->source, $this->source->getBaseDir());
		}

		$phar->stopBuffering();

		if (!chmod($temp, fileperms($temp) | 0111)) {
			throw new Exception;
		}
		
		return $temp;
	}
}