<?php

use StORM\DIConnection;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class SimplestTest extends Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * @expectedException \Exception
	 */
	public function testConnection(): void
	{
		$config = __DIR__ . '/configs/simple_config.neon';
		$tempDir = __DIR__ . '/temp';
		
		$loader = new \Nette\DI\ContainerLoader($tempDir);
		$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config): void {
			$compiler->addExtension('storm', new \StORM\Bridges\StormDI());
			$compiler->loadConfig($config);
		});
		/** @var \Nette\DI\Container $container */
		$container = new $class();
		
		$connection = $container->getByType(DIConnection::class);
		$schemaManager = $container->getByType(SchemaManager::class);
		
		Assert::same('default', $connection->getName());
		Assert::type(PDO::class, $connection->getLink());
		Assert::type(SchemaManager::class, $schemaManager);
		Assert::same($connection, $schemaManager->getConnection());
	}
	
	
}

(new SimplestTest())->run();
