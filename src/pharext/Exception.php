<?php

namespace pharext;

class Exception extends \Exception
{
	public function __construct($message = null, $code = 0, $previous = null) {
		if (!isset($message)) {
			$last_error = error_get_last();
			$message = $last_error["message"];
			if (!$code) {
				$code = $last_error["type"];
			}
		}
		parent::__construct($message, $code, $previous);
	}
}
