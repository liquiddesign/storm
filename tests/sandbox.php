<?php

require_once __DIR__ . '/../vendor/autoload.php';

\define('ENTITIES_DIR', __DIR__ . '/DB');

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

$config = __DIR__ . '/configs/single_connection.neon';
$tempDir = __DIR__ . '/temp';

Tester\Helpers::purge($tempDir);
\Tracy\Debugger::enable();

$loader = new \Nette\DI\ContainerLoader($tempDir);
$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config): void {
	$compiler->addExtension('storm', new \StORM\Bridges\StormDI());
	//$compiler->addExtension('http', new \Nette\Bridges\HttpDI\HttpExtension());
	$compiler->loadConfig($config);
});
/** @var \Nette\DI\Container $container */
$container = new $class();

/** @var \StORM\DIConnection $storm */
$storm = $container->getService('storm.default');
$storm->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);

/** @var \DB\SectorRepository $stocks */
$sector = $storm->findRepository(\DB\Sector::class);
$energy = $sector->one('energy', true);



$hash = \Nette\Utils\ArrayHash::from(['general' => true, 'name' => ['cz' => 'cc']]);

//$sector->createOne($hash);


die('ok');



