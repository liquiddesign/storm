<?php

declare(strict_types=1);

namespace StORM;

use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

/**
 * Class Collection
 * @template T of \StORM\Entity
 */
class Collection extends GenericCollection implements ICollection, IEntityParent, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	/**
	 * @phpstan-var T[]|null
	 * @var \StORM\Entity[]|null
	 */
	protected ?array $items = null;
	
	private ?\StORM\Repository $repository;
	
	/**
	 * @var \StORM\Collection[]
	 */
	private array $cache;
	
	private int $skipSelectLength;
	
	private bool $enableOptimization;
	
	private bool $enableAutojoin;
	
	private ?string $mutation;
	
	/**
	 * Collection constructor.
	 * @param \StORM\Repository $repository
	 * @param string|null $mutation
	 * @param bool $enableOptimization
	 * @param bool $enableAutojoin
	 */
	public function __construct(Repository $repository, ?string $mutation = null, bool $enableOptimization = true, bool $enableAutojoin = true)
	{
		$this->repository = $repository;
		$this->mutation = $mutation;
		$this->enableOptimization = $enableOptimization;
		$this->enableAutojoin = $enableAutojoin;
		
		$classParameters = $this->createClassParameters();
		$index = $repository::DEFAULT_ALIAS . '.' . $repository->getStructure()->getPK()->getName();
		
		$defaultSelect = $repository->getDefaultSelect($mutation);
		$this->skipSelectLength = \count($defaultSelect);
		
		parent::__construct($this->repository->getConnection(), $repository->getDefaultFrom(), $repository->getDefaultSelect($mutation), $repository->getEntityClass(), $classParameters, $index);
	}
	
	/**
	 * Get collection repository
	 */
	public function getRepository(): Repository
	{
		if (!$this->repository) {
			throw new NotExistsException($this, NotExistsException::SERIALIZE, '->setRepository()');
		}
		
		return $this->repository;
	}
	
	public function setRepository(Repository $repository): void
	{
		$this->repository = $repository;
		$this->connection = $repository->getConnection();
		$this->setFetchClass(null, $this->createClassParameters());
	}
	
	/**
	 * @param bool $needed
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function first(bool $needed = false): ?Entity
	{
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return parent::first($needed);
	}
	
	public function fetch(): ?Entity
	{
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return parent::fetch();
	}
	
	/**
	 * Convert collection to array of object
	 * @phpstan-return T[]
	 * @return \StORM\Entity[]
	 */
	public function toArray(): array
	{
		if (!$this->isLoaded()) {
			$this->load();
		}
		
		return $this->items;
	}
	
	public function getConnection(): DIConnection
	{
		return $this->getRepository()->getConnection();
	}
	
	public function isOptimization(): bool
	{
		return $this->enableOptimization;
	}
	
	/**
	 * @param \StORM\Connection $connection
	 * @throws \StORM\Exception\GeneralException
	 */
	public function setConnection(Connection $connection): void
	{
		unset($connection);
		
		throw new GeneralException('Cannot set connection to CollectionEntity, setRepository() instead.');
	}
	
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
			if ($relation->isKeyHolder() || !isset($values[$name])) {
				continue;
			}
			
			$relations[$name] = [$relation, \array_values($values[$name])];
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
		
		foreach ($clone->clear(false)->setSelect([$pkName])->toArrayOf($pkName) as $id) {
			foreach ($relations as $aux) {
				[$relation, $value] = $aux;
				$relationCollection = new RelationCollection($this->getRepository(), $relation, $id);
				$relationCollection->unrelateAll();
				
				if (!$value) {
					continue;
				}
				
				$relationCollection->relate($value);
			}
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
			$targetRepository = $this->repository->getConnection()->findRepository($relation->getTarget());
			$pkName = $targetRepository->getStructure()->getPK()->getName();
			$keys = [];
			
			foreach ($this->items as $item) {
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
	 * @param mixed[] $updates
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
	
	/**
	 * @override reset skip select length
	 * @param string[] $select
	 * @param mixed[] $values
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
	 * @return string[]
	 */
	public function getPossibleValues(string $column): array
	{
		return $this->possibleValues[$column] ?? $this->possibleValues[Repository::DEFAULT_ALIAS . '.' . $column] ?? [];
	}
	
	/**
	 * @param mixed[] $filters
	 * @param bool $silent
	 * @return static
	 */
	public function filter(array $filters, bool $silent = false): self
	{
		foreach ($filters as $name => $value) {
			$realName = Repository::FILTER_PREFIX . \ucfirst($name);
			
			if (\method_exists($this->getRepository(), $realName)) {
				\call_user_func_array([$this->getRepository(), $realName], [$value, $this]);
				
				continue;
			}
			
			if ($silent) {
				continue;
			}
			
			// throw exception
			$suggestions = '';
			$class = \get_class($this->getRepository());
			
			if ($match = Helpers::getBestSimilarString($realName, \preg_grep('/^'.Repository::FILTER_PREFIX.'/', \get_class_methods($this->getRepository())))) {
				$suggestions = " Do you mean '$match'?";
			}
			
			throw new \InvalidArgumentException("Filter in Repository $class not found.$suggestions");
		}
		
		return $this;
	}
	
	private function autojoin(): void
	{
		if (!$this->enableAutojoin) {
			return;
		}
		
		$i = 0;
		
		foreach (\array_keys($this->modifiers[self::MODIFIER_SELECT]) as $k) {
			if ($i++ < $this->skipSelectLength || !\is_string($this->modifiers[self::MODIFIER_SELECT][$k])) {
				continue;
			}
			
			$this->parseExpression($this->modifiers[self::MODIFIER_SELECT][$k]);
		}
		
		foreach ([self::MODIFIER_ORDER_BY] as $modifierName) {
			foreach (\array_keys($this->modifiers[$modifierName]) as $k) {
				if (!\is_string($k)) {
					continue;
				}
				
				$this->parseExpression($k);
			}
		}
		
		foreach ([self::MODIFIER_WHERE, self::MODIFIER_GROUP_BY] as $modifierName) {
			foreach (\array_keys($this->modifiers[$modifierName]) as $k) {
				if (!\is_string($this->modifiers[$modifierName][$k])) {
					continue;
				}
				
				$this->parseExpression($this->modifiers[$modifierName][$k]);
			}
		}
		
		return;
	}
	
	/**
	 * @return mixed[]
	 */
	private function createClassParameters(): array
	{
		$repository = $this->repository;
		$connection = $this->repository->getConnection();
		$hasMutations = $repository->getStructure()->hasMutations();
		$mutation = $this->mutation ?: $connection->getMutation();
		
		return [[], $this, $hasMutations ? $connection->getAvailableMutations() : [], $hasMutations ? $mutation : null];
	}
	
	private function parseExpression(string &$expression): void
	{
		$regexp = self::REGEXP_AUTOJOIN;
		
		$matches = [];
		\preg_match_all($regexp, $expression, $matches, \PREG_OFFSET_CAPTURE);
		
		foreach ($matches[0] as $found) {
			$aliases = $found[0];
			$offset = $found[1];
			$aliases = \substr($aliases, 0, -1);
			
			// if prefix exists i will avoid it
			if (isset($this->aliases[$aliases])) {
				continue;
			}
			
			$relationClass = $this->class;
			$aliasesList = \explode('.', $aliases);
			
			if (\count($aliasesList) > 1) {
				$expression = \substr_replace($expression, $this->getConnection()->getQuoteIdentifierChar(), $offset, 0);
				$expression = \substr_replace($expression, $this->getConnection()->getQuoteIdentifierChar(), $offset + \strlen($aliases) + 1, 0);
			}
			
			$aliasPrefix = '';
			
			foreach ($aliasesList as $alias) {
				if (!isset($this->aliases[$alias])) {
					$relation = $this->getRepository()->getSchemaManager()->getStructure($relationClass)->getRelation($alias);
					
					if (!$relation) {
						throw new NotExistsException($this, NotExistsException::RELATION, $alias, $relationClass, \array_keys($this->aliases));
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
	 * @return string[]
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
		$this->connection = null;
		$this->sth = null;
		$this->repository = null;
		$this->classParameters = $this->createClassParameters();
	}
	
	public function __clone()
	{
		$this->classParameters = $this->createClassParameters();
	}
}
