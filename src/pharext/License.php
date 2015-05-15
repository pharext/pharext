<?php

namespace pharext;

trait License
{
	function findLicense($dir, $file = null) {
		if (isset($file)) {
			return realpath("$dir/$file");
		}

		$names = [];
		foreach (["{,UN}LICEN{S,C}{E,ING}", "COPY{,ING,RIGHT}"] as $name) {
			$names[] = $this->mergeLicensePattern($name, strtolower($name));
		}
		$exts = [];
		foreach (["t{,e}xt", "rst", "asc{,i,ii}", "m{,ark}d{,own}", "htm{,l}"] as $ext) {
			$exts[] = $this->mergeLicensePattern(strtoupper($ext), $ext);
		}
		
		$pattern = "{". implode(",", $names) ."}{,.{". implode(",", $exts) ."}}";

		if (($glob = glob("$dir/$pattern", GLOB_BRACE))) {
			return current($glob);
		}
	}

	private function mergeLicensePattern($upper, $lower) {
		$pattern = "";
		$length = strlen($upper);
		for ($i = 0; $i < $length; ++$i) {
			if ($lower{$i} === $upper{$i}) {
				$pattern .= $upper{$i};
			} else {
				$pattern .= "[" . $upper{$i} . $lower{$i} . "]";
			}
		}
		return $pattern;
	}

	public function readLicense($file) {
		$text = file_get_contents($file);
		switch (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
			case "htm":
			case "html":
				$text = strip_tags($text);
				break;
		}
		return $text;
	}
}
