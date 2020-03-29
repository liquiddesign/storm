<?php

namespace StORM;

use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Structure;

abstract class Repository
{
	public const DEFAULT_ALIAS = 'this';
	
	public const FILTER_PREFIX = 'filter';
	
	/**
	 * Relation separator
	 * @internal
	 */
	public const RELATION_SEPARATOR = '_';
	
	/**
	 * @var \StORM\Meta\Structure
	 */
	protected $sqlStructure;
	
	/**
	 * @var \StORM\Connection
	 */
	protected $connection;
	
	/**
	 * @var \StORM\Connection
	 */
	protected $schemaManager;
	
	/**
	 * Repository constructor.
	 * @param \StORM\Connection $connection
	 * @param \StORM\SchemaManager $schemaManager
	 */
	public function __construct(Connection $connection, SchemaManager $schemaManager)
	{
		$this->connection = $connection;
		$this->schemaManager = $schemaManager;
	}
	
	/**
	 * Get entity class
	 * @return string
	 */
	final public function getEntityClass(): string
	{
		return $this->getStructure()->getEntityClass();
	}
	
	/**
	 * Get setted connection
	 * @return \StORM\Connection
	 */
	final public function getConnection(): Connection
	{
		return $this->connection;
	}
	
	/**
	 * Set connection
	 * @param \StORM\Connection $connection
	 */
	final public function setConnection(Connection $connection): void
	{
		$this->connection = $connection;
	}
	
	/**
	 * Get schema manager
	 * @return \StORM\SchemaManager
	 */
	final public function getSchemaManager(): SchemaManager
	{
		return $this->schemaManager;
	}
	
	/**
	 * Filter array by column names
	 * @param mixed[] $values
	 * @param bool $includePK
	 * @param bool|null $mode
	 * @return mixed[]
	 */
	public function filterByColumns(array &$values, bool $includePK = true, ?bool $mode = null): array
	{
		$columns = $this->getStructure()->getColumns($includePK);
		
		return $this->filterValues($columns, $values, $mode);
	}
	
	/**
	 * @param mixed[] $columns
	 * @param mixed[] $values
	 * @param bool|null $filterByColumns
	 * @return mixed[]
	 */
	private function filterValues(array $columns, array &$values, ?bool $filterByColumns): array
	{
		$insert = [];
		$mess = [];
		
		foreach ($values as $name => $value) {
			if (isset($columns[$name])) {
				$insert[$columns[$name]->getName()] = $value;
			} else {
				$mess[$name] = $value;
				
				if ($filterByColumns === true) {
					unset($values[$name]);
				}
			}
		}
		
		if ($filterByColumns === null && $mess) {
			throw new NotExistsException(NotExistsException::PROPERTIES, \implode(',', \array_keys($mess)));
		}
		
		if ($filterByColumns === false && $mess) {
			$insert += $mess;
		}
		
		return $insert;
	}
	
	/**
	 * Get SQL structure object
	 * @return \StORM\Meta\Structure
	 */
	final public function getStructure(): Structure
	{
		if (!$this->sqlStructure) {
			$class = Structure::getEntityClassFromRepositoryClass(static::class);
			$this->sqlStructure = $this->schemaManager->getStructure($class);
		}
		
		return $this->sqlStructure;
	}
	
	/**
	 * Create new collection
	 * @param bool $passParentToEntities
	 * @return \StORM\ICollectionEntity
	 */
	final public function many(bool $passParentToEntities = true): ICollectionEntity
	{
		return new CollectionEntity($this, $passParentToEntities);
	}
	
	/**
	 * Get entity object by condition
	 * @param string[]|string $condition
	 * @param bool $needed
	 * @param string[]|null $select
	 * @return \StORM\Entity|null
	 */
	final public function one($condition, bool $needed = false, ?array $select = null): ?Entity
	{
		$collection = $this->many(false);
		
		if ($select !== null) {
			$collection->select($select, [], true);
		}
		
		if (\is_array($condition)) {
			foreach ($condition as $property => $value) {
				$collection->addWhere(self::DEFAULT_ALIAS . '.' . $property, $value);
			}
		} else {
			$collection->where(self::DEFAULT_ALIAS . '.' . $this->getStructure()->getPK()->getName(), $condition);
		}
		
		/**
		 * @var \StORM\Entity|null|false $object
		 */
		$object = $collection->first();
		
		if (!$object && $needed) {
			throw new NotFoundException($condition);
		}
		
		if (!$object) {
			return null;
		}
		
		$object->removeParent();
		
		return $object;
	}
	
	/**
	 * Create entity row = insert row into table
	 * @param mixed[] $values
	 * @param bool|null $filterByColumns
	 * @return \StORM\Entity
	 */
	final public function createOne(array $values, ?bool $filterByColumns = null): Entity
	{
		return $this->syncOne($values, [], $filterByColumns);
	}
	
	/**
	 * Synchronize entity row by unique index, if $columnsToUpdate is null all columns are updated
	 * @param mixed[] $values
	 * @param string[]|null $updateProps
	 * @param bool|null $filterByColumns
	 * @return \StORM\Entity
	 */
	final public function syncOne(array $values, ?array $updateProps = null, ?bool $filterByColumns = null): Entity
	{
		$columns = $this->getStructure()->getColumns();
		
		$insert = $this->filterValues($columns, $values, $filterByColumns);
		
		if ($updateProps) {
			foreach ($updateProps as $key => $name) {
				if (!isset($columns[$name])) {
					throw new NotExistsException(NotExistsException::PROPERTY, $name);
				}
				
				$updateProps[$key] = $columns[$name]->getName();
			}
		}
		
		$pk = $this->getStructure()->getPK();
		
		$class = $this->getStructure()->getEntityClass();
		$vars = [];
		
		if (!isset($insert[$pk->getName()]) && !$pk->isAutoincrement()) {
			$primaryKey = $this->connection->generatePrimaryKey();
			
			if ($primaryKey) {
				$insert[$pk->getName()] = $primaryKey;
				$values[$pk->getName()] = $primaryKey;
			}
		}
		
		$sql = $this->getSqlInsert([$insert], $vars, $updateProps, false);
		
		$rowCount = $this->connection->query($sql, $vars)->rowCount();
		
		if ($pk->isAutoincrement()) {
			$values[$pk->getName()] = $this->getPrimaryKeyNextValue();
		}
		
		$hasMutations = $this->getStructure()->hasMutations();
		
		return new $class($values, $this, $hasMutations ? $this->connection->getMutation() : null, $hasMutations ? $this->connection->getAvailableMutations() : [], null, $rowCount);
	}
	
	/**
	 * Create multiple entity rows at once
	 * @param mixed[][] $manyValues
	 * @param bool $filterByColumns
	 * @param bool $ignore
	 * @param int $chunkSize
	 * @return \StORM\CollectionEntity
	 */
	final public function createMany(array $manyValues, ?bool $filterByColumns = null, bool $ignore = false, int $chunkSize = 100): CollectionEntity
	{
		return $this->syncMany($manyValues, [], $filterByColumns, $ignore, $chunkSize);
	}
	
	/**
	 * Synchronize entity rows by unique index, if $columnsToUpdate is null all columns are updated
	 * @param mixed[][] $manyValues
	 * @param string[]|null $updateProps
	 * @param bool|null $filterByColumns
	 * @param bool $ignore
	 * @param int $chunkSize
	 * @return \StORM\CollectionEntity
	 */
	final public function syncMany(array $manyValues, ?array $updateProps = null, ?bool $filterByColumns = null, bool $ignore = false, int $chunkSize = 100): CollectionEntity
	{
		$affected = 0;
		
		$columns = $this->getStructure()->getColumns();
		$pk = $this->getStructure()->getPK();
		$primaryKeys = [];
		
		if ($updateProps) {
			foreach ($updateProps as $key => $name) {
				if (!isset($columns[$name])) {
					throw new NotExistsException(NotExistsException::PROPERTY, $name);
				}
				
				$updateProps[$key] = $columns[$name]->getName();
			}
		}
		
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			$insert = [];
			
			foreach ($values as $value) {
				$row = $this->filterValues($columns, $value, $filterByColumns);
				
				if (!isset($row[$pk->getName()]) && !$pk->isAutoincrement()) {
					$primaryKey = $this->connection->generatePrimaryKey();
					
					if ($primaryKey) {
						$row[$pk->getName()] = $primaryKey;
					}
				}
				
				if (isset($row[$pk->getName()])) {
					$primaryKeys[] = $row[$pk->getName()];
				}
				
				$insert[] = $row;
			}
			
			$vars = [];
			
			$nextValue = 0;
			
			if ($pk->isAutoincrement()) {
				$this->connection->getLink()->beginTransaction();
				$nextValue = $this->getPrimaryKeyNextValue();
			}
			
			$sql = $this->getSqlInsert($insert, $vars, $updateProps, $ignore);
			$affected += $this->connection->query($sql, $vars)->rowCount();
			
			if ($pk->isAutoincrement()) {
				$primaryKeys = \range($nextValue, $this->getPrimaryKeyNextValue() - 1);
				$this->connection->getLink()->commit();
			}
			
			continue;
		}
		
		/** @var \StORM\CollectionEntity $collection */
		$collection = $primaryKeys ? $this->many()->where(self::DEFAULT_ALIAS . '.' . $pk->getName(), $primaryKeys) : $this->many()->where('1=0');
		$collection->setAffectedNumber($affected);
		
		return $collection;
	}
	
	/**
	 * @param mixed[][] $manyInserts
	 * @param mixed[] $vars
	 * @param string[]|null $onDuplicateUpdate
	 * @param bool $ignore
	 * @return string
	 */
	final public function getSqlInsert(array $manyInserts, array &$vars, ?array $onDuplicateUpdate, bool $ignore = false): string
	{
		return $this->connection->getSqlInsert($this->getStructure()->getTable()->getName(), $manyInserts, $vars, $onDuplicateUpdate, $ignore);
	}
	
	private function getPrimaryKeyNextValue(): int
	{
		if ((int) $this->connection->getLink()->lastInsertId() === 0) {
			throw new GeneralException('Cannot get last inserted ID in autoincrement PK');
		}
		
		return (int) $this->connection->getLink()->lastInsertId();
	}
	
	/**
	 * Get default SELECT modifier array for new collection
	 * @return string[]
	 */
	public function getDefaultSelect(): array
	{
		return $this->getStructure()->getColumnsSelect(self::DEFAULT_ALIAS . '.');
	}
	
	/**
	 * Get select for relation
	 * @param string $relation
	 * @return string[]
	 */
	public function getRelationSelect(string $relation): array
	{
		if (!$this->getStructure()->hasRelation($relation)) {
			throw new NotExistsException(NotExistsException::RELATION, $relation);
		}
		
		$relation = $this->getStructure()->getRelation($relation);
		$name = $relation->getName();
		$class = $relation->getTarget();
		
		return $this->getSchemaManager()->getStructure($class)->getColumnsSelect("$name.", $name. self::RELATION_SEPARATOR);
	}
	
	/**
	 * Get default FROM modifier array for new collection
	 * @return string[]
	 */
	public function getDefaultFrom(): array
	{
		return [self::DEFAULT_ALIAS => $this->getStructure()->getTable()->getName()];
	}
}
