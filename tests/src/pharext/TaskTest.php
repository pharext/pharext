<?php

namespace pharext;

require_once __DIR__."/../../autoload.php";

use pharext\Task;

class TaskTest extends \PHPUnit_Framework_TestCase
{
	function testGitClone() {
		$cmd = new Task\GitClone("https://git.php.net/repository/pecl/http/apfd.git");
		$dir = $cmd->run();

		$this->assertTrue(is_dir("$dir/.git"), "is_dir($dir/.git)");

		(new Task\Cleanup($dir))->run();
		$this->assertFalse(is_dir($dir), "is_dir($dir)");
	}

	function testPecl() {
		$cmd = new Task\StreamFetch("http://pecl.php.net/get/pecl_http", function($pct) use(&$log) {
			$log[] = $pct;
		});
		$tmp = $cmd->run();

		$this->assertTrue(is_file($tmp), "is_file($tmp)");
		$this->assertGreaterThan(1, count($log), "1 < count(\$log)");
		$this->assertContains(0, $log, "in_array(0, \$log)");
		$this->assertContains(1, $log, "in_array(1, \$log)");

		$cmd = new Task\Extract($tmp);
		$dir = $cmd->run();

		$this->assertTrue(is_dir($dir), "is_dir($dir)");
		$this->assertTrue(is_file("$dir/package.xml"), "is_file($dir/package.xml");

		$cmd = new Task\PeclFixup($dir);
		$new = $cmd->run();

		$this->assertTrue(is_dir($new), "is_dir($new)");
		$this->assertFalse(is_file("$dir/package.xml"), "is_file($dir/package.xml");
		$this->assertTrue(is_file("$new/package.xml"), "is_file($new/package.xml");

		(new Task\Cleanup($dir))->run();
		$this->assertFalse(is_dir($dir), "is_dir($dir)");
		$this->assertFalse(is_dir($new), "is_dir($new)");
	}

	function testPackage() {
		$tmp = (new Task\StreamFetch("http://pecl.php.net/get/json_post/1.0.0", function(){}))->run();
		$dir = (new Task\Extract($tmp))->run();
		$new = (new Task\PeclFixup($dir))->run();
		$src = new SourceDir\Pecl($new);
		$inf = [
			"date" => date("Y-m-d"),
			"name" => "json_post",
			"release" => "1.0.0",
			"license" => file_get_contents($src->getBaseDir()."/LICENSE"),
			"type" => "extension",
		];
		$stb = __DIR__."/../../../src/pharext_installer.php";
		$pkg = (new Task\PharBuild($src, $stb, $inf))->run();
		$gzp = (new Task\PharCompress($pkg, \Phar::GZ))->run();
		$pkg = (new Task\PharRename($pkg, ".", "json_post-1.0.0"))->run();
		$gzp = (new Task\PharRename($gzp, ".", "json_post-1.0.0"))->run();

		$this->assertTrue(is_file($pkg), "is_file($pkg)");
		$this->assertTrue(is_file($gzp), "is_file($gzp)");
	}
}
