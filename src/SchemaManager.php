<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use StORM\Exception\NotExistsException;
use StORM\Exception\SqlSchemaException;
use StORM\Meta\Column;
use StORM\Meta\Structure;

class SchemaManager
{
	/**
	 * @var array<string>
	 */
	private array $customAnnotations = [];
	
	private \Nette\Caching\Cache $cache;
	
	private ?\StORM\DIConnection $connection;
	
	/**
	 * @template T of \StORM\Entity
	 * @phpcs:ignore
	 * @var array<string,\StORM\Meta\Structure<T>>
	 */
	private array $dataModels = [];
	
	/**
	 * SchemaManager constructor.
	 * @param \StORM\DIConnection $connection
	 * @param \Nette\Caching\Storage $storage
	 */
	public function __construct(DIConnection $connection, Storage $storage)
	{
		$this->connection = $connection;
		$this->cache = new Cache($storage);
	}
	
	/**
	 * Get description of Entity in structure structure called DataModel containing properties ect.
	 * @template T of \StORM\Entity
	 * @param class-string<T> $class
	 * @param \Nette\Caching\Cache|null $cache
	 * @param \StORM\Meta\Column|null $defaultPK
	 * @return \StORM\Meta\Structure<T>
	 */
	public function getStructure(string $class, ?Cache $cache = null, ?Column $defaultPK = null): Structure
	{
		if (!isset($this->dataModels[$class]) || $cache !== null) {
			if (!\class_exists($class)) {
				throw new \InvalidArgumentException("Class $class not exists");
			}
			
			if (!\StORM\Meta\Structure::isEntityClass($class)) {
				throw new \InvalidArgumentException("$class should be child of Entity class");
			}
			
			$this->dataModels[$class] = new \StORM\Meta\Structure($class, $this, $cache ?: $this->cache, $defaultPK);
		}
		
		return $this->dataModels[$class];
	}
	
	/**
	 * Get primary key name
	 * @param string $tableName
	 */
	public function getPrimaryKeyName(string $tableName): string
	{
		$schemaName = $this->getConnection()->getDatabaseName();
		
		$vars = [
			'constraint' => 'PRIMARY',
			'table' => $tableName,
			'schema' => $schemaName,
		];
		
		$sql = 'select column_name FROM information_schema.key_column_usage where constraint_name=:constraint AND table_name=:table AND table_schema=:schema';
		$pkName = (string) $this->getConnection()->query($sql, $vars)->fetchColumn();
		
		if (!$pkName) {
			throw new SqlSchemaException("Primary key or table '$tableName' in schema '$schemaName'");
		}
		
		return $pkName;
	}
	
	/**
	 * Detect if table has autincrement on specific column
	 * @param string $tableName
	 * @param string $columnName
	 */
	public function isAutoincrement(string $tableName, string $columnName): bool
	{
		$vars = [
			'extra' => 'auto_increment',
			'table' => $tableName,
			'column' => $columnName,
			'schema' => $this->getConnection()->getDatabaseName(),
		];
		
		$sql = 'select IF(column_name = :extra,1,0) FROM information_schema.columns where column_name=:column AND table_name=:table AND table_schema=:schema';
		
		return (bool) $this->getConnection()->query($sql, $vars)->fetchColumn();
	}
	
	/**
	 * Get current connection
	 */
	public function getConnection(): DIConnection
	{
		if (!$this->connection) {
			throw new NotExistsException(null, NotExistsException::SERIALIZE, 'setConnection()');
		}
		
		return $this->connection;
	}
	
	public function setConnection(DIConnection $connection): void
	{
		$this->connection = $connection;
	}
	
	/**
	 * @param array<string> $customAnnotations
	 */
	public function setCustomAnnotations(array $customAnnotations): void
	{
		$this->customAnnotations = $customAnnotations;
	}
	
	/**
	 * @return array<string>
	 */
	public function getCustomAnnotations(): array
	{
		return $this->customAnnotations;
	}
	
	/**
	 * @return array<string>
	 */
	public function __sleep(): array
	{
		$vars = \get_object_vars($this);
		unset($vars['connection']);
		
		return \array_keys($vars);
	}
	
	public function __wakeup(): void
	{
		$this->connection = null;
	}
}
