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
	protected $items;
	
	/**
	 * @var \StORM\Repository|null
	 */
	private $repository;
	
	/**
	 * @var \StORM\Collection[]
	 */
	private $cache;
	
	/**
	 * @var int
	 */
	private $skipSelectLength;
	
	/**
	 * @var bool
	 */
	private $isOptimization;
	
	/**
	 * Collection constructor.
	 * @param \StORM\Repository $repository
	 * @param bool $isOptimization
	 */
	public function __construct(Repository $repository, bool $isOptimization = true)
	{
		$this->repository = $repository;
		$this->isOptimization = $isOptimization;
		
		$classParameters = $this->createClassParameters();
		$index = $repository->getStructure()->getPK()->getName();
		
		$defaultSelect = $repository->getDefaultSelect();
		$this->skipSelectLength = \count($defaultSelect);
		
		parent::__construct($this->repository->getConnection(), $repository->getDefaultFrom(), $repository->getDefaultSelect(), $repository->getEntityClass(), $classParameters, $index);
	}
	
	/**
	 * Get collection repository
	 * @return \StORM\Repository
	 */
	public function getRepository(): Repository
	{
		if (!$this->repository) {
			throw new GeneralException('Repository is not set. Call setRepository().');
		}
		
		return $this->repository;
	}
	
	public function setRepository(Repository $repository): void
	{
		$this->repository = $repository;
		$this->connection = $repository->getConnection();
		$this->setFetchClass(null, $this->createClassParameters());
	}
	
	public function first(bool $needed = false): ?Entity
	{
		return parent::first($needed);
	}
	
	public function fetch(): ?Entity
	{
		return parent::fetch();
	}
	
	/**
	 * Convert collection to array of object
	 * @phpstan-return T[]
	 * @return \StORM\Entity[]
	 */
	public function getItems(): array
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
	
	public function isOptimization()
	{
		return $this->isOptimization;
	}
	
	public function setConnection(Connection $connection): void
	{
		unset($connection);
		
		throw new GeneralException('Cannot set connection to CollectionEntity, setRepository() instead.');
	}
	
	/**
	 * Get object by relations, all collection will be prefetched
	 * @internal
	 * @param \StORM\Meta\Relation $relation
	 * @param string $pk
	 * @return \StORM\Entity|null
	 */
	public function getRelatedObject(Relation $relation, string $pk): ?Entity
	{
		$cacheId = $relation->getEntityClass() . $relation->getName();
		
		if (!isset($this->cache[$cacheId])) {
			$prefix = Repository::DEFAULT_ALIAS;
			$targetRepository = $this->repository->getConnection()->getRepository($relation->getTarget());
			$pkName = $targetRepository->getStructure()->getPK()->getName();
			$keys = [];
			
			foreach ($this->items as $item) {
				$keys[] = $item->getValue($relation->getName());
			}
			
			$this->cache[$cacheId] = $targetRepository->many()->setWhere("$prefix.$pkName", $keys);
		}
		
		return $this->cache[$cacheId][$pk] ?? null;
	}
	
	/**
	 * Get sql SELECT string
	 * @override adding autojoin feature
	 * @return string
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
	 * @override adding autojoin feature
	 * @return string
	 */
	public function getSqlUpdate(array &$updates, bool $ignore = false): string
	{
		$this->autojoin();
		
		return parent::getSqlUpdate($updates, $ignore);
	}
	
	/**
	 * Get sql DELETE string
	 * @override adding autojoin feature
	 * @return string
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
	
	private function autojoin(): void
	{
		$modifiersToParse = [self::MODIFIER_WHERE, self::MODIFIER_GROUP_BY, self::MODIFIER_WHERE];
		$i = 0;
		
		foreach (\array_keys($this->modifiers[self::MODIFIER_SELECT]) as $k) {
			if ($i++ < $this->skipSelectLength) {
				continue;
			}
			
			$this->parseExpression($this->modifiers[self::MODIFIER_SELECT][$k]);
		}
		
		foreach ($modifiersToParse as $modifierName) {
			foreach (\array_keys($this->modifiers[$modifierName]) as $k) {
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
		
		return [[], $this, $hasMutations ? $connection->getAvailableMutations() : [], $hasMutations ? $connection->getMutation() : null];
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
	public function __sleep()
	{
		$this->clear();
		$this->setFetchClass(null, []);
		
		$vars = \get_object_vars($this);
		unset($vars['connection'], $vars['sth'], $vars['repository']);
		
		return \array_keys($vars);
	}
}
