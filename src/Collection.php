<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

/**
 * Class Collection
 * @template T of \StORM\Entity
 * @extends \StORM\GenericCollection<T>
 * @implements \ArrayAccess<string|int, T>
 * @implements \Iterator<string|int, T>
 * @implements \StORM\ICollection<T>
 * @implements \StORM\IEntityParent<T>
 */
class Collection extends GenericCollection implements ICollection, IEntityParent, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	/**
	 * @var array<T>|null
	 */
	protected ?array $items = null;
	
	protected int $skipSelectLength;
	
	protected bool $enableOptimization = true;
	
	protected bool $enableSmartJoin = true;
	
	protected ?string $mutation;
	
	/**
	 * @phpstan-var class-string<T>
	 */
	protected string $entityClass;
	
	/**
	 * @var \StORM\Repository<T>|null
	 */
	private ?\StORM\Repository $repository;
	
	/**
	 * @var array<string, \StORM\Collection<\StORM\Entity>>
	 */
	private array $cache;
	
	/**
	 * Collection constructor.
	 * @param \StORM\Repository<T> $repository
	 * @param string|null $mutation
	 * @param array<string>|null $fallbackColumns
	 */
	public function __construct(Repository $repository, ?string $mutation = null, ?array $fallbackColumns = null)
	{
		$this->repository = $repository;
		$this->mutation = $mutation;
		$this->entityClass = $repository->getEntityClass();
		$this->classArguments = $repository->getEntityArguments($this, $mutation);
		
		$index = $repository::DEFAULT_ALIAS . '.' . $repository->getStructure()->getPK()->getName();
		
		$defaultSelect = $repository->getDefaultSelect($mutation);
		$this->skipSelectLength = \count($defaultSelect);
		
		parent::__construct(
			$this->repository->getConnection(),
			$repository->getDefaultFrom(),
			$repository->getDefaultSelect($mutation, $fallbackColumns),
			$repository->getEntityClass(),
			$this->classArguments,
			$index,
		);
	}
	
	/**
	 * @return \StORM\Repository<T>
	 */
	public function getRepository(): Repository
	{
		if (!$this->repository) {
			throw new NotExistsException($this, NotExistsException::SERIALIZE, '->setRepository()');
		}
		
		return $this->repository;
	}
	
	/**
	 * @param \StORM\Repository<T> $repository
	 */
	public function setRepository(Repository $repository): void
	{
		$this->repository = $repository;
		$this->connection = $repository->getConnection();
		$this->classArguments = $repository->getEntityArguments($this);
		$this->setFetchClass(null, $this->classArguments);
	}
	
	/**
	 * @param bool $enableSmartJoin
	 * @param string|null $entityClass
	 * @phpstan-param class-string<T>|null $entityClass
	 * @return static
	 */
	public function setSmartJoin(bool $enableSmartJoin, ?string $entityClass = null): self
	{
		$this->enableSmartJoin = $enableSmartJoin;
		
		if ($entityClass && \is_subclass_of($entityClass, Entity::class)) {
			$this->entityClass = $entityClass;
		}
		
		return $this;
	}
	
	/**
	 * @param bool $enableOptimization
	 * @return static
	 */
	public function setOptimization(bool $enableOptimization): self
	{
		$this->enableOptimization = $enableOptimization;
		
		return $this;
	}
	
	/**
	 * @param bool $needed
	 * @param string|null $columnName
	 * @param bool $load
	 * @throws \StORM\Exception\NotFoundException
	 * @return T|null
	 */
	public function first(bool $needed = false, ?string $columnName = null, bool $load = false): ?Entity
	{
		return parent::first($needed, $columnName, $load);
	}
	
	/**
	 * @param string|null $columnName
	 * @param bool $needed
	 * @param bool $load
	 * @return T|null
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function last(?string $columnName = null, bool $needed = false, bool $load = false): ?Entity
	{
		$orderByColumn = $columnName ?: $this->getRepository()->getStructure()->getPK()->getName();
		
		return parent::last($orderByColumn, $needed, $load);
	}
	
	/**
	 * @param string|null $property
	 * @param string|null $columnName
	 * @param bool $needed
	 * @return bool|string|int|null
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function lastValue(?string $property = null, ?string $columnName = null, bool $needed = false)
	{
		$orderByColumn = $columnName ?: $this->getRepository()->getStructure()->getPK()->getName();
		
		return parent::lastValue($property, $orderByColumn, $needed);
	}

	public function getConnection(): DIConnection
	{
		return $this->getRepository()->getConnection();
	}
	
	public function isOptimization(): bool
	{
		return $this->enableOptimization;
	}

	public function setConnection(Connection $connection): void
	{
		unset($connection);
		
		throw new GeneralException('Cannot set connection to CollectionEntity, setRepository() instead.');
	}
	
	/**
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function update($values, bool $ignore = false, ?string $alias = null): int
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		if (\count($values) === 0) {
			throw new \InvalidArgumentException('No value to update');
		}
		
		$relations = [];
		$result = 0;
		
		foreach ($this->getRepository()->getStructure()->getRelations() as $name => $relation) {
			if (!isset($values[$name])) {
				continue;
			}
			
			$relations[$name] = [$relation, $values[$name]];
			unset($values[$name]);
		}
		
		$values = $this->getRepository()->propertiesToColumns($values);
		
		if ($values) {
			$result = parent::update($values, $ignore, $alias);
		}
		
		if (!$relations) {
			return $result;
		}
		
		$pkName = $this->getRepository()->getStructure()->getPK()->getName();
		$clone = clone $this;
		
		foreach ($clone->clear()->setSelect([$pkName])->toArrayOf($pkName) as $id) {
			foreach ($relations as $aux) {
				[$relation, $value] = $aux;
				
				if (!$relation->isKeyHolder()) {
					$relationCollection = new RelationCollection($this->getRepository(), $relation, $id);
					$relationCollection->unrelateAll();
					
					if (!$value) {
						continue;
					}
					
					$relationCollection->relate(\array_values($value));
				} else {
					$this->getConnection()->findRepository($relation->getTarget())->syncOne($value);
				}
			}
		}
		
		if ($result !== InsertResult::NONE_AFFECTED_COUNT) {
			Arrays::invoke($this->getRepository()->onUpdate, $this->getRepository(), $this);
		}
		
		return $result;
	}
	
	/**
	 * Get object by relations, all collection will be prefetched
	 * @internal
	 * @param \StORM\Meta\Relation $relation
	 * @param string $pk
	 */
	public function getRelatedObject(Relation $relation, string $pk): ?Entity
	{
		$cacheId = $relation->getEntityClass() . $relation->getName();
		
		if (!isset($this->cache[$cacheId])) {
			$prefix = Repository::DEFAULT_ALIAS;
			
			$targetRepository = $this->getConnection()->findRepository($relation->getTarget());
			$pkName = $targetRepository->getStructure()->getPK()->getName();
			$keys = [];
			
			foreach ($this->getItems() as $item) {
				$fkValue = $item->getValue($relation->getName());
				
				if ($fkValue === null) {
					continue;
				}
				
				$keys[] = $fkValue;
			}
			
			$this->cache[$cacheId] = $targetRepository->many()->setWhere("$prefix.$pkName", $keys);
		}
		
		return $this->cache[$cacheId][$pk] ?? null;
	}
	
	/**
	 * Get sql SELECT string
	 * @override adding autojoin feature
	 */
	public function getSql(): string
	{
		$this->autojoin();
		
		return parent::getSql();
	}
	
	/**
	 * Get sql string for sql UPDATE records and bind variables in updates
	 * @param array<mixed> $updates
	 * @param bool $ignore
	 * @param string|null $alias
	 * @override adding autojoin feature
	 */
	public function getSqlUpdate(array &$updates, bool $ignore = false, ?string $alias = null): string
	{
		$this->autojoin();
		
		return parent::getSqlUpdate($updates, $ignore, $alias);
	}
	
	/**
	 * Get sql DELETE string
	 * @override adding autojoin feature
	 */
	public function getSqlDelete(): string
	{
		$this->autojoin();
		
		return parent::getSqlDelete();
	}
	
	public function delete(): int
	{
		$result = parent::delete();
		
		Arrays::invoke($this->getRepository()->onDelete, $this->getRepository(), $this);
		
		return $result;
	}
	
	/**
	 * @override reset skip select length
	 * @template X of object
	 * @param array<string>|array<\StORM\ICollection<X>> $select
	 * @param array<mixed> $values
	 * @param bool $keepIndex
	 * @return static
	 */
	public function setSelect(array $select, array $values = [], bool $keepIndex = false): self
	{
		$this->skipSelectLength = 0;
		
		return parent::setSelect($select, $values, $keepIndex);
	}
	
	/**
	 * Get possible values of column based by WHERE column IN ($possibleValues)
	 * @override adding default alias to search in array
	 * @param string $column
	 * @return array<string>
	 */
	public function getPossibleValues(string $column): array
	{
		return $this->possibleValues[$column] ?? $this->possibleValues[Repository::DEFAULT_ALIAS . '.' . $column] ?? [];
	}
	
	/**
	 * @param array<mixed> $filters
	 * @param bool $silent
	 * @return static
	 */
	public function filter(array $filters, bool $silent = false): self
	{
		foreach ($filters as $name => $value) {
			$realName = Repository::FILTER_PREFIX . Strings::firstUpper($name);
			$callback = [$this->getRepository(), $realName];
			
			if (\is_callable($callback)) {
				\call_user_func_array($callback, [$value, $this]);
				
				continue;
			}
			
			if ($silent) {
				continue;
			}
			
			// throw exception
			$suggestions = '';
			$class = \get_class($this->getRepository());
			$methods = \preg_grep('/^' . Repository::FILTER_PREFIX . '/', \get_class_methods($this->getRepository()));
			
			if ($methods && $match = Helpers::getBestSimilarString($realName, $methods)) {
				$suggestions = " Do you mean '$match'?";
			}
			
			throw new \InvalidArgumentException("Filter in Repository $class not found.$suggestions");
		}
		
		return $this;
	}
	
	private function autojoin(): void
	{
		if (!$this->enableSmartJoin) {
			return;
		}
		
		$i = 0;
		
		foreach (\array_keys($this->modifiers[self::MODIFIER_SELECT]) as $k) {
			if ($i++ < $this->skipSelectLength || !\is_string($this->modifiers[self::MODIFIER_SELECT][$k])) {
				continue;
			}
			
			$this->parseExpression($this->modifiers[self::MODIFIER_SELECT][$k]);
		}
		
		foreach (\array_keys($this->modifiers[self::MODIFIER_ORDER_BY]) as $k) {
			if (!\is_string($k)) {
				continue;
			}
			
			$this->parseExpression($k);
		}
		
		foreach ([self::MODIFIER_WHERE, self::MODIFIER_GROUP_BY] as $modifierName) {
			foreach (\array_keys($this->modifiers[$modifierName]) as $k) {
				if (!\is_string($this->modifiers[$modifierName][$k])) {
					continue;
				}
				
				$this->parseExpression($this->modifiers[$modifierName][$k]);
			}
		}
	}
	
	private function parseExpression(string &$expression): void
	{
		$regexp = self::REGEXP_AUTOJOIN;
		
		$matches = [];
		\preg_match_all($regexp, $expression, $matches, \PREG_OFFSET_CAPTURE);
		
		foreach ($matches[0] as $found) {
			$aliases = $found[0];
			$offset = $found[1];
			$aliases = Strings::substring($aliases, 0, -1);
			
			// if prefix exists i will avoid it
			if (isset($this->aliases[$aliases])) {
				continue;
			}
			
			$relationClass = $this->entityClass;
			$aliasesList = \explode('.', $aliases);
			
			if (\count($aliasesList) > 1) {
				$expression = \substr_replace($expression, $this->getConnection()->getQuoteIdentifierChar(), $offset, 0);
				$expression = \substr_replace($expression, $this->getConnection()->getQuoteIdentifierChar(), $offset + Strings::length($aliases) + 1, 0);
			}
			
			$aliasPrefix = '';
			
			foreach ($aliasesList as $alias) {
				if (!isset($this->aliases[$alias])) {
					$relation = $this->getRepository()->getSchemaManager()->getStructure($relationClass)->getRelation($alias);
					
					if (!$relation) {
						continue;
					}
					
					$realAlias = $aliasPrefix . $alias;
					$realAliasQuoted = $this->getConnection()->quoteIdentifier($realAlias);
					
					$target = $relation->getTarget();
					$source = $relation->getSource();
					
					$sourceTable = $this->getRepository()->getSchemaManager()->getStructure($source)->getTable()->getName();
					$sourceAlias = $this->tableAliases[$sourceTable] ?? $sourceTable;
					$sourceAliasQuoted = $this->getConnection()->quoteIdentifier($sourceAlias);
					
					$targetTable = $this->getRepository()->getSchemaManager()->getStructure($target)->getTable()->getName();
					$sourceKey = $relation->getSourceKey();
					$targetKey = $relation->getTargetKey();
					
					if ($relation instanceof RelationNxN) {
						$via = $relation->getVia();
						$viaTargetKey = $relation->getTargetViaKey();
						$viaSourceKey = $relation->getSourceViaKey();
						$this->join([$via], "$via.$viaSourceKey=$sourceAliasQuoted.$sourceKey");
						$this->join([$realAliasQuoted => $targetTable], "$via.$viaTargetKey=$realAliasQuoted.$targetKey");
					} else {
						$this->join([$realAliasQuoted => $targetTable], "$sourceAliasQuoted.$sourceKey=$realAliasQuoted.$targetKey");
					}
					
					$relationClass = $target;
					$aliasPrefix = "$realAlias.";
				}
			}
		}
	}
	
	/**
	 * @return array<string>
	 */
	public function __sleep(): array
	{
		$this->clear();
		$this->setFetchClass(null, []);
		
		$vars = \get_object_vars($this);
		unset($vars['connection'], $vars['sth'], $vars['repository']);
		
		return \array_keys($vars);
	}
	
	public function __wakeup(): void
	{
		parent::__wakeup();
		
		$this->repository = null;
	}
	
	public function __clone()
	{
		$this->classArguments = $this->getRepository()->getEntityArguments($this);
	}
}
