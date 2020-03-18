<?php

namespace StORM\Bridges;

use StORM\Connection;
use StORM\Exception\GeneralException;
use StORM\SchemaManager;

class StormDI extends \Nette\DI\CompilerExtension
{
	private const DEFAULT_MUTATION = 'en';
	
	/**
	 * @var string[]
	 */
	private $defaults = [
		'debug' => false,
		'autowired' => null,
		'driver' => 'mysql',
		'host' => 'localhost',
		'dbname' => null,
		'user' => 'root',
		'password' => '',
		'charset' => 'utf8',
		'collate' => 'utf8_general_ci',
		'mutations' => [self::DEFAULT_MUTATION],
		'primaryKeyGenerator' => null,
	];
	
	public function loadConfiguration(): void
	{
		$configs = $this->getConfig();
		$first = true;
		
		foreach ((array) $configs as $name => $config) {
			$config += $this->defaults;
			
			if ($config['dbname'] === null) {
				throw new GeneralException('"dbname" is not set in extension configuration');
			}
			
			if ($config['autowired'] === null) {
				$config['autowired'] = $first;
			}
			
			$this->setupDatabase($name, $config);
			$first = false;
		}
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		
		$builder->addDefinition($this->prefix('storm'))->setClass(SchemaManager::class)->setAutowired(true);
	}
	
	/**
	 * @param string $name
	 * @param string[] $config
	 */
	private function setupDatabase(string $name, array $config): void
	{
		$driver = $config['driver'];
		$databaseName = $config['dbname'];
		$charset = $config['charset'];
		$collate = $config['collate'];
		$host = $config['host'];
	
		$dsn = "$driver:dbname=$databaseName;host=$host";
		
		$attributes = [
			\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE $collate",
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		];
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		$connection = $builder->addDefinition($this->prefix($name))->setClass(Connection::class)->setAutowired($config['autowired'])
			->addSetup('connect', [$name, $dsn, $config['user'], $config['password'], $attributes])
			->addSetup('setDebug', [$config['debug']]);
		
		if ($config['debug']) {
			$connection->addSetup('@Tracy\Bar::addPanel', [
				new \Nette\DI\Definitions\Statement(StormTracy::class, ['name' => $name,]),
			]);
		}
		
		if ($config['primaryKeyGenerator']) {
			$connection->addSetup('setPrimaryKeyGenerator', [$config['primaryKeyGenerator']]);
		}
		
		if ($config['mutations']) {
			$connection->addSetup('setAvailableMutations', [$config['mutations']]);
		}
		
		return;
	}
}
