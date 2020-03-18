<?php

namespace StORM;

use StORM\Exception\InvalidStateException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

class CollectionRelation extends CollectionEntity implements ICollectionRelation, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	/**
	 * @var \StORM\Meta\Relation
	 */
	protected $relation;
	
	/**
	 * @var string
	 */
	protected $keyValue;
	
	/**
	 * CollectionRelation constructor.
	 * @param \StORM\Repository $repository
	 * @param \StORM\Meta\Relation $relation
	 * @param string $keyValue
	 */
	public function __construct(Repository $repository, Relation $relation, string $keyValue)
	{
		$this->relation = $relation;
		$this->keyValue = $keyValue;
		
		if ($relation->isKeyHolder()) {
			throw new InvalidStateException(InvalidStateException::KEY_HOLDER_NOT_ALLOWED);
		}
		
		parent::__construct($repository->getConnection()->getRepositoryByEntityClass($relation->getTarget()));
	}
	
	protected function init(): void
	{
		parent::init();
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaTargetKey = $this->relation->getTargetViaKey();
			$viaSourceKey = $this->relation->getSourceViaKey();
			$targetKey = $this->relation->getTargetKey();
			$this->join(['via' => $via], "via.$viaTargetKey=this.$targetKey");
			$this->where("via.$viaSourceKey", $this->keyValue);
		} else {
			$this->where($this->relation->getTargetKey(), [$this->keyValue]);
		}
		
		return;
	}
	
	/**
	 * Relate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param mixed[] $primaryKeys
	 * @param bool $checkKeys
	 * @param string|null $primaryKeyName You can specify column name and method will generate primary keys for that columns
	 * @return int
	 */
	public function relate(array $primaryKeys, bool $checkKeys = true, ?string $primaryKeyName = null): int
	{
		if ($this->isLoaded()) {
			$this->clear();
		}
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaTargetKey = $this->relation->getTargetViaKey();
			$viaSourceKey = $this->relation->getSourceViaKey();
			
			$inserts = [];
			
			foreach ($primaryKeys as $index => $key) {
				$row = \is_array($key) ? \array_merge([$viaSourceKey => $this->keyValue, $viaTargetKey => $index], $key) : [$viaSourceKey => $this->keyValue, $viaTargetKey => $key];
				
				if ($primaryKeyName) {
					$row[$primaryKeyName] = $this->getConnection()->generatePrimaryKey();
				}
				
				$inserts[] = $row;
			}
			
			$vars = [];
			$sql = $this->getConnection()->getSqlInsert($via, $inserts, $vars, null, !$checkKeys);
			$sth = $this->getConnection()->query($sql, $vars);
			
			return $sth->rowCount();
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$targetKey = $this->relation->getTargetKey();
		
		/** @var \StORM\CollectionEntity $collection */
		$collection = $this->getConnection()->getRepositoryByEntityClass($class)->many();

		return $collection->where('this.uuid', \array_values($primaryKeys))->update([$targetKey => $this->keyValue], !$checkKeys, false);
	}
	
	/**
	 * Unrelate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param mixed[] $primaryKeys
	 * @return int
	 */
	public function unrelate(array $primaryKeys): int
	{
		if ($this->isLoaded()) {
			$this->clear();
		}
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaTargetKey = $this->relation->getTargetViaKey();
			$viaSourceKey = $this->relation->getSourceViaKey();
			
			return $this->getConnection()->rows()->from(['this' => $via])->where("this.$viaSourceKey", $this->keyValue)->where("this.$viaTargetKey", \array_values($primaryKeys))->delete();
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$targetKey = $this->relation->getTargetKey();
		
		return $this->getConnection()->getRepositoryByEntityClass($class)->many()->where('this.uuid', \array_values($primaryKeys))->update([$targetKey => null]);
	}
	
	/**
	 * Unrelate all records
	 * Collection will be cleared before relate
	 * @return int
	 */
	public function unrelateAll(): int
	{
		if ($this->isLoaded()) {
			$this->clear();
		}
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaSourceKey = $this->relation->getSourceViaKey();
			
			return $this->getConnection()->rows()->from(['this' => $via])->where("this.$viaSourceKey", $this->keyValue)->delete();
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$sourceKey = $this->relation->getSourceKey();
		$targetKey = $this->relation->getTargetKey();
		
		return $this->getConnection()->getRepositoryByEntityClass($class)->many()->where("this.$sourceKey", $this->keyValue)->update([$targetKey => null]);
	}
}
