<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
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
	
	/**
	 * @var array<callable> Occurs when entity in repository is deleted
	 */
	public array $onDelete = [];
	
	/**
	 * @var array<callable> Occurs when entity in repository is created
	 */
	public array $onCreate = [];
	
	/**
	 * @var array<callable> Occurs when entity in repository is updated
	 */
	public array $onUpdate = [];
	
	protected ?\StORM\Meta\Structure $sqlStructure = null;
	
	protected \StORM\DIConnection $connection;
	
	protected \StORM\SchemaManager $schemaManager;
	
	/**
	 * @var array<mixed>
	 */
	private array $injectedArguments = [];
	
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
	
	final public function injectEntityArguments(...$arguments): void
	{
		$this->injectedArguments = $arguments;
	}
	
	/**
	 * @param \StORM\IEntityParent $parent
	 * @param string|null $mutation
	 * @return array<mixed>
	 */
	final public function getEntityArguments(IEntityParent $parent, ?string $mutation = null): array
	{
		$connection = $this->getConnection();
		$hasMutations = $this->getStructure()->hasMutations();
		$mutation = $mutation ?: $this->getConnection()->getMutation();
		
		return \array_merge([[]], $this->injectedArguments, [$parent, $hasMutations ? $connection->getAvailableMutations() : [], $hasMutations ? $mutation : null]);
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
	 * @param array<string>|null $fallbackColumns
	 * @phpstan-return \StORM\Collection<T>
	 * @return \StORM\Collection|array<T>
	 */
	final public function many(?string $mutation = null, ?array $fallbackColumns = null): Collection
	{
		/** @phpstan-ignore-next-line */
		return new Collection($this, $mutation, $fallbackColumns);
	}
	
	/**
	 * Get entity object by condition
	 * @param array<string|int|float>|string|int $condition
	 * @param bool $needed
	 * @param array<string>|null $select
	 * @param string|null $mutation
	 * @throws \StORM\Exception\NotFoundException
	 * @phpstan-return T|null
	 */
	final public function one($condition, bool $needed = false, ?array $select = null, ?string $mutation = null): ?Entity
	{
		$conditionValidTypes = ['array', 'string', 'integer'];
		
		if (!Arrays::contains($conditionValidTypes, \gettype($condition))) {
			throw new \InvalidArgumentException('Invalid argument type "' . \gettype($condition) . '", valid types: ' . \implode(', ', $conditionValidTypes));
		}
		
		$collection = $this->many($mutation)->setOptimization(false);
		
		if ($select !== null) {
			$collection->setSelect($select, [], true);
		}
		
		if (!\is_array($condition)) {
			$condition = [$this->getStructure()->getPK()->getName() => $condition];
		}
		
		return $collection
			->whereMatch($condition, self::DEFAULT_ALIAS . '.')
			->first($needed);
	}
	
	final public function getRepository(): Repository
	{
		return $this;
	}
	
	/**
	 * Create entity row = insert row into table
	 * @param array<mixed>|object $values
	 * @param bool|null $filterByColumns
	 * @param bool $ignore
	 * @param array<mixed> $checkKeys
	 * @param array<mixed> $primaryKeyNames
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
	 * @param array<mixed>|object $values
	 * @param array<string>|array<\StORM\Literal>|null $updateProps
	 * @param bool|null $filterByColumns
	 * @param bool|null $ignore
	 * @param array<mixed> $checkKeys
	 * @param array<mixed> $primaryKeyNames
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
			$values = Helpers::filterInputArray($values, \array_keys($columns), $filterByColumns);
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
		$beforeId = $this->getPrimaryKeyNextValue();
		
		$sth = $this->connection->query($sql, $vars);
		$rowCount = $sth->rowCount();
		$sth->closeCursor();
		
		if (!isset($values[$pk->getPropertyName()]) && ($pk->isAutoincrement() || ($pk->isAutoincrement() === null && $beforeId !== $this->getPrimaryKeyNextValue()))) {
			$values[$pk->getPropertyName()] = $this->getPrimaryKeyNextValue();
		}
		
		/** @var \StORM\Entity $object */
		/** @phpstan-var T $object */
		$object = new $class(...[$values] + $this->getEntityArguments($this));
		
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
		
		if ($object->getParent() instanceof Collection) {
			if ($rowCount !== InsertResult::NONE_AFFECTED_COUNT) {
				Arrays::invoke($this->onUpdate, $this, $object->getParent());
			}
		} else {
			Arrays::invoke($this->onCreate, $this, [$values[$pk->getPropertyName()]]);
		}
		
		return $object;
	}
	
	/**
	 * Create multiple entity rows at once
	 * @param array<array<mixed>>|array<object> $manyValues
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
	 * @param array<array<mixed>>|array<object> $manyValues
	 * @param array<string>|array<\StORM\Literal>|null $updateProps
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
		
		/** @var array<mixed>|object $values */
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
					$value = Helpers::filterInputArray($value, \array_keys($columns), $filterByColumns);
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
						throw new \InvalidArgumentException('Primary key not found for joining relations.');
					}
					
					$collectionRelation = new RelationCollection($this, $this->getStructure()->getRelation($name), $primaryKey);
					$collectionRelation->relate($keys);
				}
				
				$insert[] = $row;
			}
			
			$vars = [];
			
			$beforeId = $this->getPrimaryKeyNextValue();
			
			$sql = $this->getSqlInsert($insert, $vars, $updateProps, $ignore);
			$sth = $this->connection->query($sql, $vars);
			$affected += $sth->rowCount();
			$sth->closeCursor();
			
			if ($ignore || $updateProps !== [] || (!$pk->isAutoincrement() && ($pk->isAutoincrement() !== null || $beforeId === $this->getPrimaryKeyNextValue()))) {
				continue;
			}

			$primaryKeys = \range($this->getPrimaryKeyNextValue() - $affected, $this->getPrimaryKeyNextValue() - 1);
		}
		
		/** @var \StORM\Collection $collection */
		$collection = $primaryKeys ? $this->many()->setWhere(self::DEFAULT_ALIAS . '.' . $pk->getName(), $primaryKeys) : $this->many()->setWhere('1=0');
		$collection->setAffectedNumber($affected);
		
		if ($affected !== InsertResult::NONE_AFFECTED_COUNT) {
			Arrays::invoke($this->onUpdate, $this, $collection);
		}
		
		return $collection;
	}
	
	/**
	 * @param array<array<mixed>> $manyInserts
	 * @param array<mixed> $vars
	 * @param array<string>|null $onDuplicateUpdate
	 * @param bool $ignore
	 */
	final public function getSqlInsert(array $manyInserts, array &$vars, ?array $onDuplicateUpdate, bool $ignore = false): string
	{
		return $this->connection->getSqlInsert($this->getStructure()->getTable()->getName(), $manyInserts, $vars, $onDuplicateUpdate, $ignore);
	}
	
	/**
	 * Get default SELECT modifier array for new collection
	 * @param string|null $mutation
	 * @param array<string>|null $fallbackColumns
	 * @return array<string>
	 */
	public function getDefaultSelect(?string $mutation = null, ?array $fallbackColumns = null): array
	{
		return $this->getStructure()->getColumnsSelect(self::DEFAULT_ALIAS . '.', '', $mutation, $fallbackColumns);
	}
	
	/**
	 * Get select for relation
	 * @param string $relation
	 * @return array<string>
	 */
	public function getRelationSelect(string $relation): array
	{
		if (!$this->getStructure()->hasRelation($relation)) {
			throw new NotExistsException(null, NotExistsException::RELATION, $relation, $this->getEntityClass(), \array_keys($this->getStructure()->getRelations()));
		}
		
		$relation = $this->getStructure()->getRelation($relation);
		$name = $relation->getName();
		$class = $relation->getTarget();
		
		return $this->getSchemaManager()->getStructure($class)->getColumnsSelect("$name.", $name . self::RELATION_SEPARATOR);
	}
	
	/**
	 * Get default FROM modifier array for new collection
	 * @return array<string>
	 */
	public function getDefaultFrom(): array
	{
		return [self::DEFAULT_ALIAS => $this->getStructure()->getTable()->getName()];
	}
	
	/**
	 * Call user filters on collection
	 * @param \StORM\Collection $collection
	 * @param array<array<mixed>> $filters
	 * @param bool $silent
	 * @phpstan-return \StORM\Collection<T>
	 */
	public function filter(Collection $collection, array $filters, bool $silent = false): Collection
	{
		foreach ($filters as $name => $value) {
			if (!\is_string($name)) {
				continue;
			}
			
			$realName = Repository::FILTER_PREFIX . Strings::firstUpper($name);
			
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
			
			if ($match = Helpers::getBestSimilarString($realName, \preg_grep('/^' . Repository::FILTER_PREFIX . '/', \get_class_methods($this)))) {
				$suggestions = " Do you mean '$match'?";
			}
			
			throw new \InvalidArgumentException("Filter in Repository $class not found.$suggestions");
		}
		
		return $collection;
	}
	
	/**
	 * Convert properties to columns
	 * @param array<mixed> $values
	 * @param bool $skipArrays
	 * @return array<mixed>
	 */
	public function propertiesToColumns(array $values, bool $skipArrays = false): array
	{
		$columns = $this->getStructure()->getColumns(true);
		$return = [];
		
		foreach ($values as $name => $value) {
			if (!isset($columns[$name])) {
				$return[$name] = $value;
				
				continue;
			}
			
			$return[$skipArrays && \is_array($value) ? $name : $columns[$name]->getName()] = $value;
		}
		
		return $return;
	}
	
	private function getPrimaryKeyNextValue(): int
	{
		return (int) $this->connection->getLink()->lastInsertId();
	}
	
	/**
	 * @param array<mixed> $values
	 * @param bool $sync
	 * @return array<array<int>>|array<array<string>>
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
	 * @return array<string>
	 * @throws \StORM\Exception\GeneralException
	 */
	public function __sleep(): array
	{
		throw new GeneralException('StORM connections are unserializable');
	}
}
