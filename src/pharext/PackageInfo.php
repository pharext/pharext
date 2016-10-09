<?php

namespace pharext;

trait PackageInfo
{
	/**
	 * @return array
	 */
	public function findPackageInfo($path) {
		try {
			if (!strlen($name = $this->findPackageName($path, $header))) {
				return [];
			}
			if (!$release = $this->findPackageReleaseVersion($path, $header, strtoupper($name))) {
				return [];
			}
		} catch (Exception $e) {
			return [];
		}

		return compact("name", "release");
	}

	private function findPackageName($path, &$header = null) {
		$grep_res = (new ExecCmd("grep"))->run(
			array_merge(
				["-HEo", "phpext_[^ ]+_ptr"],
				explode("\n", (new ExecCmd("find"))->run([
					$path, "-type", "f", "-name", "php_*.h"
				])->getOutput())
			)
		)->getOutput();
		if (!list($header, $phpext_ptr) = explode(":", $grep_res)) {
			return [];
		}
		if (!$name = substr($phpext_ptr, 7, -4)) {
			return [];
		}
		return $name;

	}

	private function findPackageReleaseVersion($path, $header, $uname) {
		$cpp_tmp = new Tempfile("cpp");
		$cpp_hnd = $cpp_tmp->getStream();
		fprintf($cpp_hnd, "#include \"%s\"\n", $header);
		fprintf($cpp_hnd, "#if defined(PHP_PECL_%s_VERSION)\n", $uname);
		fprintf($cpp_hnd, "PHP_PECL_%s_VERSION\n", $uname);
		fprintf($cpp_hnd, "#elif defined(PHP_%s_VERSION)\n", $uname);
		fprintf($cpp_hnd, "PHP_%s_VERSION\n", $uname);
		fprintf($cpp_hnd, "#elif defined(%s_VERSION)\n", $uname);
		fprintf($cpp_hnd, "%s_VERSION\n", $uname);
		fprintf($cpp_hnd, "#endif\n");
		fflush($cpp_hnd);
		$php_inc = (new ExecCmd((PHP_BINARY ?? "php")."-config"))->run([
			"--includes"
		])->getOutput();
		$ext_inc = (new ExecCmd("find"))->run([
			$path, "-not", "-path", "*/.*", "-type", "d", "-printf", "-I'%p' "
		])->getOutput();
		$cpp_res = (new ExecCmd("cpp $php_inc $ext_inc"))->run([
			"-E", $cpp_tmp
		])->getOutput();
		return trim(substr($cpp_res, strrpos($cpp_res, "\n")), "\"\n");
	}
}
