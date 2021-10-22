<?php

declare(strict_types=1);

namespace StORM\Bridges;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use StORM\DIConnection;
use StORM\Exception\IContextException;
use StORM\SchemaManager;

class StormDI extends \Nette\DI\CompilerExtension
{
	private const DEFAULT_MUTATION = 'en';
	
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'connections' => Expect::arrayOf(Expect::structure([
				'driver' => Expect::string('mysql'),
				'host' => Expect::string()->required(),
				'dbname' => Expect::string()->required(),
				'user' => Expect::string()->required(),
				'password' => Expect::string('')->required(),
				'charset' => Expect::string('utf8'),
				'collate' => Expect::string('utf8_general_ci'),
				'primaryKeyGenerator' => Expect::string(null),
				'mutations' => Expect::arrayOf('string')->default([self::DEFAULT_MUTATION => ''])->min(1)->mergeDefaults(false),
			]))->min(1),
			'schema' => Expect::structure([
				'customAnnotations' => Expect::arrayOf('string')->default([]),
			]),
			'events' => Expect::arrayOf(Expect::structure([
				'events' => Expect::arrayOf('string')->min(1)->required(),
				'repositories' => Expect::arrayOf('string')->min(1)->required(),
				'callback' => Expect::arrayOf('string')->min(2)->max(2),
			]))->default([]),
			'debug' => Expect::bool(false),
		]);
	}
	
	public function loadConfiguration(): void
	{
		$first = true;
		
		/** @var \stdClass $configuration */
		$configuration = $this->getConfig();
		
		foreach ($configuration->connections as $name => $config) {
			$config = (array) $config;
			
			if (!isset($config['autowired'])) {
				$config['autowired'] = $first;
			}
			
			$this->setupDatabase($name, $config, $configuration->debug);
			$first = false;
		}
		
		/** @var \Nette\DI\ContainerBuilder $builder */
		$builder = $this->getContainerBuilder();
		$schemaManager = $builder->addDefinition($this->prefix('schemaManager'))->setType(SchemaManager::class)->setAutowired(true);
		
		if ($configuration->schema->customAnnotations) {
			$schemaManager->addSetup('setCustomAnnotations', [$configuration->schema->customAnnotations]);
		}
		
		if ($configuration->debug) {
			\Tracy\Debugger::getBlueScreen()->addPanel(static function (?\Throwable $e) {
				if ($e instanceof IContextException && $e->getContext()) {
					return [
						'tab' => \get_class($e->getContext()),
						'panel' => $e->getContext()->dump(true),
					];
				}
				
				return [];
			});
		}
		
		if ($configuration->events && $builder->hasDefinition('application.application')) {
			/** @var \Nette\DI\Definitions\ServiceDefinition $app */
			$app = $builder->getDefinition('application.application');
			
			foreach ($configuration->events as $eventConfiguration) {
				foreach ($eventConfiguration->repositories as $repository) {
					foreach ($eventConfiguration->events as $event) {
						$app->addSetup("@$repository::$$event" . '[]', [
							$eventConfiguration->callback,
						]);
					}
				}
			}
		}
		
		return;
	}
	
	/**
	 * @param string $name
	 * @param string[] $config
	 * @param bool $debug
	 */
	private function setupDatabase(string $name, array $config, bool $debug): void
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
		$attributes = ['@container', $name, $dsn, $config['user'], $config['password'], $attributes];
		$connection = $builder->addDefinition($this->prefix($name))->setFactory(DIConnection::class, $attributes)->setAutowired($config['autowired'])
			->addSetup('setDebug', [$debug]);
		
		if ($debug) {
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
