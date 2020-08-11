<?php // @codingStandardsIgnoreLine

require_once __DIR__ . '/../vendor/autoload.php';

$configFile = __DIR__ . '/configs/multiple_connections.neon';
$sourceFile = __DIR__ . '/_sql/_test_storm.sql';

$config = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['default'];
$config2 = \Nette\Neon\Neon::decode(\file_get_contents($configFile))['storm']['connections']['test'];

// create test DB and fill with test data
$pdo = new \PDO("$config[driver]:host=$config[host]", $config['user'], $config['password']);
$pdo->query("CREATE DATABASE IF NOT EXISTS $config[dbname] CHARACTER SET $config[charset] COLLATE $config[collate]");
$pdo->query("CREATE DATABASE IF NOT EXISTS $config2[dbname] CHARACTER SET $config2[charset] COLLATE $config2[collate]");
$pdo->query("USE $config[dbname]");
$pdo->query(\file_get_contents($sourceFile));
