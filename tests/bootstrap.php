<?php // @codingStandardsIgnoreLine


use Tracy\Debugger;

\define('TEMP_DIR', __DIR__ . '/temp/' . \getmypid());
\define('CONFIGS_DIR', __DIR__ . '/configs');
\define('ENTITIES_DIR', __DIR__ . '/DB');

@\mkdir(\dirname(\TEMP_DIR));
@\mkdir(\TEMP_DIR);

require_once __DIR__ . '/../vendor/autoload.php';

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

Debugger::enable();
Tester\Environment::setup();
Tester\Helpers::purge(\TEMP_DIR);
