<?php

namespace pharext;

/**
 * Simple task interface
 */
interface Task
{
	public function run($verbose = false);
}
