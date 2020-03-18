<?php

use Tracy\Debugger;

// prepare containers
$containers = [];

/** @var \SplFileInfo $file */
foreach (\Nette\Utils\Finder::findFiles('*.neon')->in(\CONFIGS_DIR) as $file) {
	Tester\Helpers::purge(\TEMP_DIR);
	$loader = new \Nette\DI\ContainerLoader(\TEMP_DIR);
	$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($file): void {
		$compiler->addExtension('storm', new \StORM\Bridges\StormDI());
		$compiler->addConfig(['parameters' => ['appDir' => __DIR__]]);
		$compiler->loadConfig((string)$file);
	}, $file->getFilename());
	
	/** @var \Nette\DI\Container $container */
	$container = new $class();
	
	if (!Debugger::getBar()->getPanel(\Nette\Bridges\DITracy\ContainerPanel::class)) {
		Debugger::getBar()->addPanel(new \Nette\Bridges\DITracy\ContainerPanel($container));
	}
	
	$name = $file->getBasename('.neon');
	$containers[$name] = [
		'container' => $container,
		'name' => $name,
	];
}

return $containers;
