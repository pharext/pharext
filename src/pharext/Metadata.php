<?php

namespace pharext;

class Metadata
{
	static function version() {
		return "4.1.4";
	}

	static function header() {
		return sprintf("pharext v%s (c) Michael Wallner <mike@php.net>", self::version());
	}

	static function date() {
		return gmdate("Y-m-d");
	}

	static function all() {
		return [
			"version" => self::version(),
			"header" => self::header(),
			"date" => self::date(),
		];
	}
}
