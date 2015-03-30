<?php

namespace pharext\Task;

use pharext\Exception;
use pharext\Task;
use pharext\Tempfile;

class PaxFixup implements Task
{
	private $source;

	public function __construct($source) {
		$this->source = $source;
	}

	private function openArchive($source) {
		$hdr = file_get_contents($source, false, null, 0, 3);
		if ($hdr === "\x1f\x8b\x08") {
			$fd = fopen("compress.zlib://$source", "r");
		} elseif ($hdr === "BZh") {
			$fd = fopen("compress.bzip2://$source", "r");
		} else {
			$fd = fopen($source, "r");
		}
		if (!is_resource($fd)) {
			throw new Exception;
		}
		return $fd;
	}

	public function run($verbose = false) {
		if ($verbose !== false) {
			printf("Fixing up a tarball with global pax header ...\n");
		}
		$temp = new Tempfile("paxfix");
		stream_copy_to_stream($this->openArchive($this->source),
			$temp->getStream(), -1, 1024);
		$temp->closeStream();
		return (new Extract((string) $temp))->run($verbose);
	}
}