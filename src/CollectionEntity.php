<?php

namespace StORM;

use StORM\Exception\NotExistsException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

class CollectionEntity extends Collection implements ICollectionEntity, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	/**
	 * @var \StORM\Repository
	 */
	private $repository;
	
	/**
	 * @var \StORM\CollectionEntity[]
	 */
	private $cache;
	
	/**
	 * @var int
	 */
	private $skipSelectLength = 0;
	
	/**
	 * Collection constructor.
	 * @param \StORM\Repository $repository
	 * @param bool $passParentToEntities
	 */
	public function __construct(Repository $repository, bool $passParentToEntities = true)
	{
		$this->repository = $repository;
		$connection = $repository->getConnection();
		$hasMutations = $repository->getStructure()->hasMutations();
		$classParameters = [[], $repository, $hasMutations ? $connection->getMutation() : null, $hasMutations ? $connection->getAvailableMutations() : []];
		
		if ($passParentToEntities) {
			$classParameters[] = $this;
		}
		
		$index = $repository->getStructure()->getPK()->getName();
		$defaultSelect = $repository->getDefaultSelect();
		$this->skipSelectLength = \count($defaultSelect);
		
		parent::__construct($connection, $repository->getDefaultFrom(), $repository->getDefaultSelect(), $repository->getEntityClass(), $classParameters, $index);
	}
	
	/**
	 * Call user filters on repository
	 * @param mixed[][] $filters
	 * @param bool $silent
	 * @return \StORM\ICollectionEntity
	 */
	public function filter(array $filters, bool $silent = false): ICollectionEntity
	{
		$collection = $this;
		
		foreach ($filters as $name => $value) {
			$realName = Repository::FILTER_PREFIX . \ucfirst($name);
			
			if (\method_exists($this->repository, $realName)) {
				\call_user_func_array([$this->repository, $realName], [$value, $collection]);
				
				continue;
			}
			
			if (!$silent) {
				throw new NotExistsException(NotExistsException::FILTER, $realName, $this->class, \preg_grep('/^'.Repository::FILTER_PREFIX.'/', \get_class_methods($this->repository)));
			}
		}
		
		return $collection;
	}
	
	
	/**
	 * Get collection repository
	 * @return \StORM\Repository
	 */
	final public function getRepository(): Repository
	{
		return $this->repository;
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
			$targetRepository = $this->getConnection()->getRepositoryByEntityClass($relation->getTarget());
			$pkName = $targetRepository->getStructure()->getPK()->getName();
			$keys = [];
			
			foreach ($this->items as $item) {
				$keys[] = $item->getValue($relation->getName());
			}
			
			$this->cache[$cacheId] = $targetRepository->many()->setWhere("$prefix.$pkName", $keys);
		}
		
		return $this->cache[$cacheId][$pk] ?? null;
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
			
			/**
			 * @var \StORM\Entity $relationClass
			 */
			$relationClass = $this->class;
			$aliasesList = \explode('.', $aliases);
			
			if (\count($aliasesList) > 1) {
				$expression = \substr_replace($expression, $this->connection->getQuoteIdentifierChar(), $offset, 0);
				$expression = \substr_replace($expression, $this->connection->getQuoteIdentifierChar(), $offset + \strlen($aliases) + 1, 0);
			}
			
			$aliasPrefix = '';
			
			foreach ($aliasesList as $alias) {
				if (!isset($this->aliases[$alias])) {
					$relation = $this->repository->getSchemaManager()->getStructure($relationClass)->getRelation($alias);
					
					if (!$relation) {
						throw new NotExistsException(NotExistsException::RELATION, $alias, $relationClass, \array_keys($this->aliases));
					}
					
					$realAlias = $aliasPrefix . $alias;
					$realAliasQuoted = $this->connection->quoteIdentifier($realAlias);
					
					/**
					 * @var \StORM\Entity $target
					 */
					$target = $relation->getTarget();
					
					/**
					 * @var \StORM\Entity $source
					 */
					$source = $relation->getSource();
					
					$sourceTable = $this->repository->getSchemaManager()->getStructure($source)->getTable()->getName();
					$sourceAlias = $this->tableAliases[$sourceTable] ?? $sourceTable;
					$sourceAliasQuoted = $this->connection->quoteIdentifier($sourceAlias);
					
					$targetTable = $this->repository->getSchemaManager()->getStructure($target)->getTable()->getName();
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
	 * @param mixed[] $select
	 * @param mixed[] $values
	 * @param bool $keepIndex
	 * @return \StORM\ICollection
	 */
	public function setSelect(array $select, array $values = [], bool $keepIndex = false): ICollection
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
	 * Update all record equals condition and return number of affected rows
	 * @override adding filter by columns
	 * @param string[] $values
	 * @param bool $ignore
	 * @param bool|null $filterByColumns
	 * @return int
	 */
	public function update(array $values, bool $ignore = false, ?bool $filterByColumns = null): int
	{
		$columns = $this->repository->filterByColumns($values, true, $filterByColumns);
		
		return parent::update($columns, $ignore);
	}
}
