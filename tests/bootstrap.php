<?php // @codingStandardsIgnoreLine


use Tracy\Debugger;

\define('TEMP_ROOT_DIR', __DIR__ . '/temp');
\define('TEMP_DIR', __DIR__ . '/temp/' . \basename($_SERVER["SCRIPT_FILENAME"], '.php'));
\define('CONFIGS_DIR', __DIR__ . '/configs');
\define('ENTITIES_DIR', __DIR__ . '/DB');

require_once __DIR__ . '/../vendor/autoload.php';

if (!\is_dir(\TEMP_ROOT_DIR)) {
	\mkdir(\TEMP_ROOT_DIR);
}

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

Debugger::enable();
Tester\Environment::setup();
Tester\Helpers::purge(\TEMP_DIR);
