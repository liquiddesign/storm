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

// create databases
$configFile = __DIR__ . '/configs/multiple_connections.neon';
$sourceFile = __DIR__ . '/_sql/_test_storm.sql';
$config = \Nette\Neon\Neon::decode(\file_get_contents($configFile));
$host = $config['storm']['default']['host'];
$user = $config['storm']['default']['user'];
$password = $config['storm']['default']['password'];
$driver = $config['storm']['default']['driver'];
$charset = $config['storm']['default']['charset'];
$collate = $config['storm']['default']['collate'];
$database1 = $config['storm']['default']['dbname'];
$database2 = $config['storm']['test']['dbname'];
$pdo = new \PDO("$driver:host=$host", $user, $password);
$pdo->query("CREATE DATABASE IF NOT EXISTS $database2 CHARACTER SET $charset COLLATE $collate");
$pdo->query("CREATE DATABASE IF NOT EXISTS $database1 CHARACTER SET $charset COLLATE $collate");
$pdo->query("USE $database1");
$pdo->query(\file_get_contents($sourceFile));

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

Debugger::enable();
Tester\Environment::setup();
Tester\Helpers::purge(\TEMP_DIR);
