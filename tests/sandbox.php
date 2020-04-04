<?php

require_once __DIR__ . '/../vendor/autoload.php';

\define('ENTITIES_DIR', __DIR__ . '/DB');

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

$config = __DIR__ . '/configs/single_connection.neon';
$tempDir = __DIR__ . '/temp';

Tester\Helpers::purge($tempDir);

$loader = new \Nette\DI\ContainerLoader($tempDir);
$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config): void {
	$compiler->addExtension('storm', new \StORM\Bridges\StormDI());
	//$compiler->addExtension('http', new \Nette\Bridges\HttpDI\HttpExtension());
	$compiler->loadConfig($config);
});
/** @var \Nette\DI\Container $container */
$container = new $class();

/** @var \StORM\Connection $storm */
$storm = $container->getService('storm.default');

/** @var \DB\StockRepository $stocks */
$stocks = $storm->getRepository(\DB\StockRepository::class);
