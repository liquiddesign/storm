<?php

require_once __DIR__ . '/bootstrap.php';

foreach (\glob(__DIR__ . '/*Test.phpt') as $file) {
	include $file;
}