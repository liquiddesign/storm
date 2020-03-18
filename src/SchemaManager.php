<?php

namespace StORM;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Meta\SqlStructure;

class SchemaManager implements \JsonSerializable
{
	/**
	 * @var \Nette\Caching\IStorage
	 */
	private $cache;
	
	/**
	 * @var \StORM\Connection
	 */
	private $connection;
	
	/**
	 * @var \StORM\Meta\SqlStructure[]
	 */
	private $dataModels = [];
	
	/**
	 * SchemaManager constructor.
	 * @param \StORM\Connection $connection
	 * @param \Nette\Caching\IStorage $cache
	 */
	public function __construct(Connection $connection, IStorage $cache)
	{
		$this->connection = $connection;
		$cache = new Cache($cache);
		$this->cache = $cache;
	}
	
	/**
	 * Get description of Entity in structure structure called DataModel containing properties ect.
	 * @param string $class
	 * @return \StORM\Meta\SqlStructure
	 */
	public function getSqlStructure(string $class): SqlStructure
	{
		if (!isset($this->dataModels[$class])) {
			if (!\class_exists($class)) {
				throw new NotExistsException(NotExistsException::CLASS_NAME, $class);
			}
			
			if (!\StORM\Meta\SqlStructure::isEntityClass($class)) {
				throw new \InvalidArgumentException("$class should be child of Entity class");
			}
			
			$this->dataModels[$class] = new \StORM\Meta\SqlStructure($class, $this, $this->cache);
		}
		
		return $this->dataModels[$class];
	}
	
	/**
	 * Get primary key name
	 * @param string $tableName
	 * @return string
	 */
	public function getPrimaryKeyName(string $tableName): string
	{
		$schemaName = $this->connection->getDatabaseName();
		
		$vars = [
			'constraint' => 'PRIMARY',
			'table' => $tableName,
			'schema' => $schemaName,
		];
		
		$sql = 'select column_name FROM information_schema.key_column_usage where constraint_name=:constraint AND table_name=:table AND table_schema=:schema';
		$pkName = (string) $this->connection->query($sql, $vars)->fetchColumn(0);
		
		if (!$pkName) {
			throw new GeneralException("Table '$tableName' or primary key doeasnt exists in '$schemaName'");
		}
		
		return $pkName;
	}
	
	/**
	 * Detect if table has autincrement on specific column
	 * @param string $tableName
	 * @param string $columnName
	 * @return string
	 */
	public function isAutoincrement(string $tableName, string $columnName): string
	{
		$vars = [
			'extra' => 'auto_increment',
			'table' => $tableName,
			'column' => $columnName,
			'schema' => $this->connection->getDatabaseName(),
		];
		
		$sql = 'select IF(column_name = :extra,1,0) FROM information_schema.columns where column_name=:column AND table_name=:table AND table_schema=:schema';
		
		return (bool) $this->connection->query($sql, $vars)->fetchColumn(0);
	}
	
	/**
	 * Serialize all SQL structures by registred repositories
	 * @return mixed[]
	 * @throws \ReflectionException
	 */
	public function jsonSerialize(): array
	{
		$json = [];
		
		foreach ($this->connection->getAllRepositories() as $repository) {
			$json[$repository->getEntityClass()] = $repository->getSqlStructure()->jsonSerialize();
		}
		
		return $json;
	}
	
	/**
	 * Get current connection
	 * @return \StORM\Connection
	 */
	public function getConnection(): \StORM\Connection
	{
		return $this->connection;
	}
}
