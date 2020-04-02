<?php

// "nette/http":"~3.0.0",
require_once __DIR__ . '/../vendor/autoload.php';

\define('ENTITIES_DIR', __DIR__ . '/DB');

foreach (\glob(\ENTITIES_DIR . '/*.php') as $file) {
	require_once $file;
}

$config = __DIR__ . '/configs/simple_config.neon';
$tempDir = __DIR__ . '/temp';

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
$storm->getLink()->query('USE _test_storm2');

$storm->query(file_get_contents(__DIR__ . '/_sql/_test_storm.sql'));


//dump($alerts->getPropertiesAnnotations());


// difference and affected => doplnit

//$container->getService('http.request');

/** @var \Nette\Http\Response $response */
//$response = $container->getService('http.response');

// url
// dam jen uri
// rootApi
// nactu si service
// mam tam action? a je tam opravneni na api? validace pristupu?
// mam filter
// pagings
// post, put
// vracim a serializuju, jinak value, result type = entity, collection, value