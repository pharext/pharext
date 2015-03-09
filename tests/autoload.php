<?php
set_include_path(__DIR__."/src:".get_include_path());
spl_autoload_register(function($class) {
	if (strncmp($class, "pharext\\", strlen("pharext\\"))) {
		return false;
	}
	return include __DIR__."/../src/".strtr($class, "_\\", "//").".php";
});
