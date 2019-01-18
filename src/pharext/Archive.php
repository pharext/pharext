<?php

namespace pharext;

use ArrayAccess;
use IteratorAggregate;
use RecursiveDirectoryIterator;
use SplFileInfo;

use pharext\Exception;

class Archive implements ArrayAccess, IteratorAggregate
{
	const HALT_COMPILER = "\137\137\150\141\154\164\137\143\157\155\160\151\154\145\162\50\51\73";
	const SIGNED = 0x10000;
	const SIG_MD5    = 0x0001;
	const SIG_SHA1   = 0x0002;
	const SIG_SHA256 = 0x0003;
	const SIG_SHA512 = 0x0004;
	const SIG_OPENSSL= 0x0010;

	private static $siglen = [
		self::SIG_MD5    => 16,
		self::SIG_SHA1   => 20,
		self::SIG_SHA256 => 32,
		self::SIG_SHA512 => 64,
		self::SIG_OPENSSL=> 0
	];

	private static $sigalg = [
		self::SIG_MD5    => "md5",
		self::SIG_SHA1   => "sha1",
		self::SIG_SHA256 => "sha256",
		self::SIG_SHA512 => "sha512",
		self::SIG_OPENSSL=> "openssl"
	];

	private static $sigtyp = [
		self::SIG_MD5    => "MD5",
		self::SIG_SHA1   => "SHA-1",
		self::SIG_SHA256 => "SHA-256",
		self::SIG_SHA512 => "SHA-512",
		self::SIG_OPENSSL=> "OpenSSL",
	];

	const PERM_FILE_MASK = 0x01ff;
	const COMP_FILE_MASK = 0xf000;
	const COMP_GZ_FILE   = 0x1000;
	const COMP_BZ2_FILE  = 0x2000;

	const COMP_PHAR_MASK= 0xf000;
	const COMP_PHAR_GZ  = 0x1000;
	const COMP_PHAR_BZ2 = 0x2000;

	private $file;
	private $fd;
	private $stub;
	private $manifest;
	private $signature;
	private $extracted;

	function __construct($file = null) {
		if (strlen($file)) {
			$this->open($file);
		}
	}

	function open($file) {
		if (!$this->fd = @fopen($file, "r")) {
			throw new Exception;
		}
		$this->file = $file;
		$this->stub = $this->readStub();
		$this->manifest = $this->readManifest();
		$this->signature = $this->readSignature();
	}

	function getIterator() {
		return new RecursiveDirectoryIterator($this->extract());
	}

	function extract() {
		return $this->extracted ?: $this->extractTo(new Tempdir("archive"));
	}

	function extractTo($dir) {
		if ((string) $this->extracted == (string) $dir) {
			return $this->extracted;
		}
		foreach ($this->manifest["entries"] as $file => $entry) {
			fseek($this->fd, $this->manifest["offset"]+$entry["offset"]);
			$path = "$dir/$file";
			$copy = stream_copy_to_stream($this->fd, $this->outFd($path, $entry["flags"]), $entry["csize"]);
			if ($entry["osize"] != $copy) {
				throw new Exception("Copied '$copy' of '$file', expected '{$entry["osize"]}' from '{$entry["csize"]}");
			}

			$crc = hexdec(hash_file("crc32b", $path));
			if ($crc !== $entry["crc32"]) {
				throw new Exception("CRC mismatch of '$file': '$crc' != '{$entry["crc32"]}");
			}

			chmod($path, $entry["flags"] & self::PERM_FILE_MASK);
			touch($path, $entry["stamp"]);
		}
		return $this->extracted = $dir;
	}

	function offsetExists($o) {
		return isset($this->entries[$o]);
	}

	function offsetGet($o) {
		$this->extract();
		return new SplFileInfo($this->extracted."/$o");
	}

	function offsetSet($o, $v) {
		throw new Exception("Archive is read-only");
	}

	function offsetUnset($o) {
		throw new Exception("Archive is read-only");
	}

	function getSignature() {
		/* compatible with Phar::getSignature() */
		return [
			"hash_type" => self::$sigtyp[$this->signature["flags"]],
			"hash" => strtoupper(bin2hex($this->signature["hash"])),
		];
	}

	function getPath() {
		/* compatible with Phar::getPath() */
		return new SplFileInfo($this->file);
	}

	function getMetadata($key = null) {
		if (isset($key)) {
			return $this->manifest["meta"][$key];
		}
		return $this->manifest["meta"];
	}

	private function outFd($path, $flags) {
		$dirn = dirname($path);
		if (!is_dir($dirn) && !@mkdir($dirn, 0777, true)) {
			throw new Exception;
		}
		if (!$fd = @fopen($path, "w")) {
			throw new Exception;
		}
		switch ($flags & self::COMP_FILE_MASK) {
		case self::COMP_GZ_FILE:
			if (!@stream_filter_append($fd, "zlib.inflate")) {
				throw new Exception;
			}
			break;
		case self::COMP_BZ2_FILE:
			if (!@stream_filter_append($fd, "bz2.decompress")) {
				throw new Exception;
			}
			break;
		}
		return $fd;
	}

	private function readVerified($fd, $len) {
		if ($len != strlen($data = fread($fd, $len))) {
			throw new Exception("Unexpected EOF");
		}
		return $data;
	}

	private function readFormat($format, $fd, $len) {
		if (false === ($data = @unpack($format, $this->readVerified($fd, $len)))) {
			throw new Exception;
		}
		return $data;
	}

	private function readSingleFormat($format, $fd, $len) {
		return current($this->readFormat($format, $fd, $len));
	}

	private function readStringBinary($fd) {
		if (($length = $this->readSingleFormat("V", $fd, 4))) {
			return $this->readVerified($this->fd, $length);
		}
		return null;
	}

	private function readSerializedBinary($fd) {
		if (($length = $this->readSingleFormat("V", $fd, 4))) {
			if (false === ($data = unserialize($this->readVerified($fd, $length)))) {
				throw new Exception;
			}
			return $data;
		}
		return null;
	}

	private function readStub() {
		$stub = "";
		while (!feof($this->fd)) {
			$line = fgets($this->fd);
			$stub .= $line;
			if (false !== stripos($line, self::HALT_COMPILER)) {
				/* check for '?>' on a separate line */
				if ('?>' === $this->readVerified($this->fd, 2)) {
					$stub .= '?>' . fgets($this->fd);
				} else {
					fseek($this->fd, -2, SEEK_CUR);
				}
				break;
			}
		}
		return $stub;
	}

	private function readManifest() {
		$current = ftell($this->fd);
		$header = $this->readFormat("Vlen/Vnum/napi/Vflags", $this->fd, 14);
		$alias = $this->readStringBinary($this->fd);
		$meta = $this->readSerializedBinary($this->fd);
		$entries = [];
		for ($i = 0; $i < $header["num"]; ++$i) {
			$this->readEntry($entries);
		}
		$offset = ftell($this->fd);
		if (($length = $offset - $current - 4) != $header["len"]) {
			throw new Exception("Manifest length read was '$length', expected '{$header["len"]}'");
		}
		return $header + compact("alias", "meta", "entries", "offset");
	}

	private function readEntry(array &$entries) {
		if (!count($entries)) {
			$offset = 0;
		} else {
			$last = end($entries);
			$offset = $last["offset"] + $last["csize"];
		}
		$file = $this->readStringBinary($this->fd);
		if (!strlen($file)) {
			throw new Exception("Empty file name encountered at offset '$offset'");
		}
		$header = $this->readFormat("Vosize/Vstamp/Vcsize/Vcrc32/Vflags", $this->fd, 20);
		$meta = $this->readSerializedBinary($this->fd);
		$entries[$file] =  $header + compact("meta", "offset");
	}

	private function readSignature() {
		fseek($this->fd, -8, SEEK_END);
		$sig = $this->readFormat("Vflags/Z4magic", $this->fd, 8);
		$end = ftell($this->fd);

		if ($sig["magic"] !== "GBMB") {
			throw new Exception("Invalid signature magic value '{$sig["magic"]}");
		}

 		switch ($sig["flags"]) {
		case self::SIG_OPENSSL:
			fseek($this->fd, -12, SEEK_END);
			if (($hash = $this->readSingleFormat("V", $this->fd, 4))) {
				$offset = 4 + $hash;
				fseek($this->fd, -$offset, SEEK_CUR);
				$hash = $this->readVerified($this->fd, $hash);
				fseek($this->fd, 0, SEEK_SET);
				$valid = openssl_verify($this->readVerified($this->fd, $end - $offset - 8),
					$hash, @file_get_contents($this->file.".pubkey")) === 1;
			}
			break;

		case self::SIG_MD5:
		case self::SIG_SHA1:
		case self::SIG_SHA256:
		case self::SIG_SHA512:
			$offset = 8 + self::$siglen[$sig["flags"]];
			fseek($this->fd, -$offset, SEEK_END);
			$hash = $this->readVerified($this->fd, self::$siglen[$sig["flags"]]);
			$algo = hash_init(self::$sigalg[$sig["flags"]]);
			fseek($this->fd, 0, SEEK_SET);
			hash_update_stream($algo, $this->fd, $end - $offset);
			$valid = hash_final($algo, true) === $hash;
			break;

		default:
			throw new Exception("Invalid signature type '{$sig["flags"]}");
		}

		return $sig + compact("hash", "valid");
	}
}
