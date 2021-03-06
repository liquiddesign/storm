<?php

declare(strict_types=1);

namespace StORM;

use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Exception\NotFoundException;
use StORM\Exception\SqlSchemaException;
use StORM\Meta\Structure;

/**
 * Class Repository
 * @template T of \StORM\Entity
 */
abstract class Repository implements IEntityParent
{
	public const DEFAULT_ALIAS = 'this';
	
	public const FILTER_PREFIX = 'filter';
	
	/**
	 * Relation separator
	 * @internal
	 */
	public const RELATION_SEPARATOR = '_';
	
	protected ?\StORM\Meta\Structure $sqlStructure = null;
	
	protected \StORM\DIConnection $connection;
	
	protected \StORM\SchemaManager $schemaManager;
	
	/**
	 * Repository constructor
	 * @param \StORM\DIConnection $connection
	 * @param \StORM\SchemaManager $schemaManager
	 */
	public function __construct(DIConnection $connection, SchemaManager $schemaManager)
	{
		$this->connection = $connection;
		$this->schemaManager = $schemaManager;
	}
	
	/**
	 * Get entity class
	 * @phpstan-return class-string<T>
	 */
	final public function getEntityClass(): string
	{
		return $this->getStructure()->getEntityClass();
	}
	
	/**
	 * Get setted connection
	 */
	final public function getConnection(): DIConnection
	{
		return $this->connection;
	}
	
	/**
	 * Set connection
	 * @param \StORM\DIConnection $connection
	 */
	final public function setConnection(DIConnection $connection): void
	{
		$this->connection = $connection;
	}
	
	/**
	 * Get schema manager
	 */
	final public function getSchemaManager(): SchemaManager
	{
		return $this->schemaManager;
	}
	
	/**
	 * Get SQL structure object
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
	 * @param string|null $mutation
	 * @param bool $optimization
	 * @param bool $autojoin
	 * @phpstan-return \StORM\Collection<T>
	 */
	final public function many(?string $mutation = null, bool $optimization = true, bool $autojoin = true): Collection
	{
		return new Collection($this, $mutation, $optimization, $autojoin);
	}
	
	/**
	 * Get entity object by condition
	 * @param string[]|string|int $condition
	 * @param bool $needed
	 * @param string[]|null $select
	 * @param string|null $mutation
	 * @throws \StORM\Exception\NotFoundException
	 * @phpstan-return T|null
	 */
	final public function one($condition, bool $needed = false, ?array $select = null, ?string $mutation = null): ?Entity
	{
		$conditionValidTypes = ['array', 'string', 'integer'];
		
		if (!\in_array(\gettype($condition), $conditionValidTypes)) {
			throw new \InvalidArgumentException('Invalid argument type "' . \gettype($condition) . '", valid types: ' . \implode(', ', $conditionValidTypes));
		}
		
		/** @var \StORM\Collection $collection */
		$collection = $this->many($mutation, false);
		
		if ($select !== null) {
			$collection->setSelect($select, [], true);
		}
		
		if (\is_array($condition)) {
			$collection->match($condition, self::DEFAULT_ALIAS . '.');
		} else {
			$collection->setWhere(self::DEFAULT_ALIAS . '.' . $this->getStructure()->getPK()->getName(), $condition);
		}
		
		/**
		 * @phpstan-var T|null|false $object
		 * @var \StORM\Entity|null|false $object
		 */
		$object = $collection->first();
		
		if (!$object && $needed) {
			throw new NotFoundException($collection, \is_string($condition) ? [$this->getStructure()->getPK()->getName() => $condition] : $condition, static::class);
		}
		
		if (!$object) {
			return null;
		}
		
		return $object;
	}
	
	final public function getRepository(): Repository
	{
		return $this;
	}
	
	/**
	 * Create entity row = insert row into table
	 * @param mixed[]|object $values
	 * @param bool|null $filterByColumns
	 * @param bool $ignore
	 * @param mixed[] $checkKeys
	 * @param mixed[] $primaryKeyNames
	 * @throws \StORM\Exception\NotFoundException
	 * @phpstan-return T
	 */
	final public function createOne($values, ?bool $filterByColumns = false, bool $ignore = false, array $checkKeys = [], array $primaryKeyNames = []): Entity
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		return $this->syncOne($values, [], $filterByColumns, $ignore, $checkKeys, $primaryKeyNames);
	}
	
	/**
	 * Synchronize entity row by unique index, if $columnsToUpdate is null all columns are updated
	 * @param mixed[]|object $values
	 * @param string[]|\StORM\Literal[]|null $updateProps
	 * @param bool|null $filterByColumns
	 * @param bool|null $ignore
	 * @param mixed[] $checkKeys
	 * @param mixed[] $primaryKeyNames
	 * @throws \StORM\Exception\NotFoundException
	 * @phpstan-return T
	 */
	final public function syncOne($values, ?array $updateProps = null, ?bool $filterByColumns = false, ?bool $ignore = null, array $checkKeys = [], array $primaryKeyNames = []): Entity
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		$columns = $this->getStructure()->getColumns();
		
		$joinRelations = $this->createRelations($values, $updateProps === null);
		
		if ($filterByColumns !== null) {
			$values = Helpers::filterInputArray($values, \array_keys($columns), (bool)$filterByColumns);
		}
		
		$insert = $this->propertiesToColumns($values);
		$updateRelations = [];
		
		if ($updateProps) {
			foreach ($updateProps as $key => $name) {
				if ($name instanceof Literal) {
					continue;
				}
				
				if (isset($joinRelations[$name])) {
					$updateRelations[$name] = $name;
					unset($updateProps[$name]);
					
					continue;
				}
				
				if (!isset($columns[$name])) {
					throw new NotExistsException(null, NotExistsException::PROPERTY, $name, $this->getEntityClass(), \array_keys($columns));
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
				$values[$pk->getPropertyName()] = $primaryKey;
			}
		}
		
		$sql = $this->getSqlInsert([$insert], $vars, $updateProps, $ignore ?? !$updateProps);
		$beforeId = $this->getPrimaryKeyNextValue(false);
		
		$rowCount = $this->connection->query($sql, $vars)->rowCount();
		
		if (!isset($values[$pk->getPropertyName()]) && ($pk->isAutoincrement() || ($pk->isAutoincrement() === null && $beforeId !== $this->getPrimaryKeyNextValue(false)))) {
			$values[$pk->getPropertyName()] = $this->getPrimaryKeyNextValue();
		}
		
		$hasMutations = $this->getStructure()->hasMutations();
		
		
		/** @var \StORM\Entity $object */
		/** @phpstan-var T $object */
		$object = new $class($values, $this, $hasMutations ? $this->connection->getAvailableMutations() : [], $hasMutations ? $this->connection->getMutation() : null);
		
		if ($rowCount !== InsertResult::INSERT_AFFECTED_COUNT) {
			$collection = $this->many()->where($this->getStructure()->getPK()->getName(), $object->getPk());
			$collection->setAffectedNumber($rowCount);
			$object->setParent($collection);
		}
		
		// update all subject
		
		foreach ($joinRelations as $relation => $keys) {
			if ($object->$relation instanceof IRelation && ($rowCount === InsertResult::INSERT_AFFECTED_COUNT || $updateProps === null || isset($updateRelations[$relation]))) {
				$object->$relation->unrelateAll();
				
				if ($keys) {
					$object->$relation->relate($keys, $checkKeys[$relation] ?? true, $primaryKeyNames[$relation] ?? null);
				}
			}
		}
		
		return $object;
	}
	
	/**
	 * Create multiple entity rows at once
	 * @param mixed[][]|object[] $manyValues
	 * @param bool $filterByColumns
	 * @param bool $ignore
	 * @param int $chunkSize
	 * @phpstan-return \StORM\Collection<T>
	 * @throws \StORM\Exception\NotFoundException
	 */
	final public function createMany(array $manyValues, ?bool $filterByColumns = false, bool $ignore = false, int $chunkSize = 100): Collection
	{
		return $this->syncMany($manyValues, [], $filterByColumns, $ignore, $chunkSize);
	}
	
	/**
	 * Synchronize entity rows by unique index, if $columnsToUpdate is null all columns are updated
	 * @param mixed[][]|object[] $manyValues
	 * @param string[]|\StORM\Literal[]|null $updateProps
	 * @param bool|null $filterByColumns
	 * @param bool $ignore
	 * @param int $chunkSize
	 * @phpstan-return \StORM\Collection<T>
	 * @throws \StORM\Exception\NotFoundException
	 */
	final public function syncMany(array $manyValues, ?array $updateProps = null, ?bool $filterByColumns = false, bool $ignore = false, int $chunkSize = 100): Collection
	{
		$affected = 0;
		
		$columns = $this->getStructure()->getColumns();
		$pk = $this->getStructure()->getPK();
		$primaryKeys = [];
		
		if ($updateProps) {
			foreach ($updateProps as $key => $name) {
				if (!isset($columns[$name])) {
					throw new NotExistsException(null, NotExistsException::PROPERTY, $name, $this->getEntityClass(), \array_keys($columns));
				}
				
				$updateProps[$key] = $columns[$name]->getName();
			}
		}
		
		/** @var mixed[]|object $values */
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			$insert = [];
			
			if (\is_object($values)) {
				$values = Helpers::toArrayRecursive($values);
			}
			
			if (!\is_array($values)) {
				$type = \gettype($values);
				
				throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
			}
			
			foreach ($values as $value) {
				$primaryKey = null;
				$joinRelations = $this->createRelations($value, $updateProps === null);
				
				if ($filterByColumns !== null) {
					$value = Helpers::filterInputArray($value, \array_keys($columns), (bool)$filterByColumns);
				}
				
				$row = $this->propertiesToColumns($value);
				
				if (!isset($row[$pk->getName()]) && !$pk->isAutoincrement()) {
					$primaryKey = $this->connection->generatePrimaryKey();
					
					if ($primaryKey) {
						$row[$pk->getName()] = $primaryKey;
					}
				}
				
				if (isset($row[$pk->getName()])) {
					$primaryKeys[] = $primaryKey = $row[$pk->getName()];
				}
				
				foreach ($joinRelations as $name => $keys) {
					if (!$primaryKey) {
						throw new \InvalidArgumentException("Primary key not found for joining relations.");
					}
					
					$collectionRelation = new RelationCollection($this, $this->getStructure()->getRelation($name), $primaryKey);
					$collectionRelation->relate($keys);
				}
				
				$insert[] = $row;
			}
			
			$vars = [];
			
			
			$beforeId = $this->getPrimaryKeyNextValue(false);
			
			
			$sql = $this->getSqlInsert($insert, $vars, $updateProps, $ignore);
			$affected += $this->connection->query($sql, $vars)->rowCount();
			
			if (!$ignore && $updateProps === array() && ($pk->isAutoincrement() || ($pk->isAutoincrement() === null && $beforeId !== $this->getPrimaryKeyNextValue(false)))) {
				$primaryKeys = \range($this->getPrimaryKeyNextValue() - $affected, $this->getPrimaryKeyNextValue() - 1);
			}
			
			continue;
		}
		
		/** @var \StORM\Collection $collection */
		$collection = $primaryKeys ? $this->many()->setWhere(self::DEFAULT_ALIAS . '.' . $pk->getName(), $primaryKeys) : $this->many()->setWhere('1=0');
		$collection->setAffectedNumber($affected);
		
		return $collection;
	}
	
	/**
	 * @param mixed[][] $manyInserts
	 * @param mixed[] $vars
	 * @param string[]|null $onDuplicateUpdate
	 * @param bool $ignore
	 */
	final public function getSqlInsert(array $manyInserts, array &$vars, ?array $onDuplicateUpdate, bool $ignore = false): string
	{
		return $this->connection->getSqlInsert($this->getStructure()->getTable()->getName(), $manyInserts, $vars, $onDuplicateUpdate, $ignore);
	}
	
	/**
	 * Get default SELECT modifier array for new collection
	 * @param string|null $mutation
	 * @return string[]
	 */
	public function getDefaultSelect(?string $mutation = null): array
	{
		return $this->getStructure()->getColumnsSelect(self::DEFAULT_ALIAS . '.', '', $mutation);
	}
	
	/**
	 * Get select for relation
	 * @param string $relation
	 * @return string[]
	 */
	public function getRelationSelect(string $relation): array
	{
		if (!$this->getStructure()->hasRelation($relation)) {
			throw new NotExistsException(null, NotExistsException::RELATION, $relation, $this->getEntityClass(), \array_keys($this->getStructure()->getRelations()));
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
	
	/**
	 * Call user filters on collection
	 * @param \StORM\Collection $collection
	 * @param mixed[][] $filters
	 * @param bool $silent
	 * @phpstan-return \StORM\Collection<T>
	 */
	public function filter(Collection $collection, array $filters, bool $silent = false): Collection
	{
		foreach ($filters as $name => $value) {
			$realName = Repository::FILTER_PREFIX . \ucfirst($name);
			
			if (\method_exists($this, $realName)) {
				\call_user_func_array([$this, $realName], [$value, $collection]);
				
				continue;
			}
			
			if ($silent) {
				continue;
			}
			
			// throw exception
			$suggestions = '';
			$class = static::class;
			
			if ($match = Helpers::getBestSimilarString($realName, \preg_grep('/^'.Repository::FILTER_PREFIX.'/', \get_class_methods($this)))) {
				$suggestions = " Do you mean '$match'?";
			}
			
			throw new \InvalidArgumentException("Filter in Repository $class not found.$suggestions");
		}
		
		return $collection;
	}
	
	/**
	 * Convert properties to columns
	 * @param mixed[] $values
	 * @return mixed[]
	 */
	public function propertiesToColumns(array $values): array
	{
		$columns = $this->getStructure()->getColumns(true);
		$return = [];
		
		foreach ($values as $name => $value) {
			if (!isset($columns[$name])) {
				$return[$name] = $value;
				
				continue;
			}
			
			$return[$columns[$name]->getName()] = $value;
		}
		
		return $return;
	}
	
	private function getPrimaryKeyNextValue(bool $check = true): int
	{
		if ($check && (int) $this->connection->getLink()->lastInsertId() === 0) {
			throw new SqlSchemaException('Cannot get last inserted ID in autoincrement PK');
		}
		
		return (int) $this->connection->getLink()->lastInsertId();
	}
	
	/**
	 * @param mixed[] $values
	 * @param bool $sync
	 * @return int[][]|string[][]
	 * @throws \StORM\Exception\NotFoundException
	 */
	private function createRelations(array &$values, bool $sync): array
	{
		$joinRelations = [];
		
		foreach ($this->getStructure()->getRelations() as $relation) {
			$name = $relation->getPropertyName();
			
			if (!isset($values[$name]) || !\is_array($values[$name])) {
				continue;
			}
			
			if ($relation->isKeyHolder()) {
				$values[$name] = $this->getConnection()->findRepository($relation->getTarget())->syncOne($values[$name], $sync ? null : []);
			} else {
				$joinRelations[$name] = \array_values($values[$name]);
				unset($values[$name]);
			}
		}
		
		return $joinRelations;
	}
	
	/**
	 * Sleep
	 * @return string[]
	 * @throws \StORM\Exception\GeneralException
	 */
	public function __sleep(): array
	{
		throw new GeneralException('StORM connections are unserializable');
	}
}
