<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\Task;
use pharext\Tempfile;

/**
 * Fetch a remote archive
 */
class StreamFetch implements Task
{
	/**
	 * @var string
	 */
	private $source;

	/**
	 * @var callable
	 */
	private $progress;

	/**
	 * @param string $source remote file location
	 * @param callable $progress progress callback
	 */
	public function __construct($source, callable $progress) {
		$this->source = $source;
		$this->progress = $progress;
	}

	private function createStreamContext() {
		$progress = $this->progress;

		/* avoid bytes_max bug of older PHP versions */
		$maxbytes = 0;
		return stream_context_create([],["notification" => function($notification, $severity, $message, $code, $bytes_cur, $bytes_max) use($progress, &$maxbytes) {
			if ($bytes_max > $maxbytes) {
				$maxbytes = $bytes_max;
			}
			switch ($notification) {
				case STREAM_NOTIFY_CONNECT:
					$progress(0);
					break;
				case STREAM_NOTIFY_PROGRESS:
					$progress($maxbytes > 0 ? $bytes_cur/$maxbytes : .5);
					break;
				case STREAM_NOTIFY_COMPLETED:
					/* this is sometimes not generated, why? */
					$progress(1);
					break;
			}
		}]);
	}

	/**
	 * @param bool $verbose
	 * @return \pharext\Task\Tempfile
	 * @throws \pharext\Exception
	 */
	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Fetching %s ...\n", $this->source);
		}
		$context = $this->createStreamContext();

		if (!$remote = @fopen($this->source, "r", false, $context)) {
			throw new Exception;
		}
		
		$local = new Tempfile("remote");
		if (!stream_copy_to_stream($remote, $local->getStream())) {
			throw new Exception;
		}
		$local->closeStream();

		/* STREAM_NOTIFY_COMPLETED is not generated, see above */
		call_user_func($this->progress, 1);

		return $local;
	}
}
