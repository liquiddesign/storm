<?php

declare(strict_types=1);

namespace StORM;

use StORM\Exception\InvalidStateException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

/**
 * Class CollectionRelation
 * @template T of \StORM\Entity
 */
class RelationCollection extends Collection implements IRelation, ICollection, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	protected \StORM\Meta\Relation $relation;
	
	protected string $keyValue;
	
	/**
	 * CollectionRelation constructor.
	 * @param \StORM\Repository $repository
	 * @param \StORM\Meta\Relation $relation
	 * @param string|int $keyValue
	 */
	public function __construct(Repository $repository, Relation $relation, $keyValue)
	{
		$this->relation = $relation;
		$this->keyValue = (string) $keyValue;
		
		if ($relation->isKeyHolder()) {
			throw new InvalidStateException($this, InvalidStateException::KEY_HOLDER_NOT_ALLOWED);
		}
		
		parent::__construct($repository->getConnection()->findRepository($relation->getTarget()));
	}
	
	/**
	 * Relate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param string[]|int[]|string[][]|int[][] $primaryKeys
	 * @param bool $checkKeys
	 * @param string|null $primaryKeyName You can specify column name and method will generate primary keys for that columns
	 * @throws \StORM\Exception\NotFoundException
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
			$count = $sth->rowCount();
			unset($sth);
			
			return $count;
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$targetKey = $this->relation->getTargetKey();
		
		/** @var \StORM\ICollection $collection */
		$collection = $this->getRepository()->getConnection()->findRepository($class)->many()->setWhere('this.uuid', \array_values($primaryKeys));

		if ($checkKeys) {
			$pkName = $this->getRepository()->getConnection()->findRepository($class)->getStructure()->getPK()->getName();
			$resultArray = (clone $collection)->toArrayOf($pkName);
			$desiredArray = \array_combine(\array_values($primaryKeys), \array_values($primaryKeys));
			
			if ($diff = \array_diff_key($desiredArray, $resultArray)) {
				throw new NotFoundException($this, $diff, $desiredArray);
			}
		}
		
		return $collection->update([$targetKey => $this->keyValue]);
	}
	
	/**
	 * Unrelate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param string[]|int[] $primaryKeys
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
			
			return $this->getConnection()->rows()->setFrom(['this' => $via])->where("this.$viaSourceKey", $this->keyValue)->where("this.$viaTargetKey", \array_values($primaryKeys))->delete();
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$targetKey = $this->relation->getTargetKey();
		
		return $this->getRepository()->getConnection()->findRepository($class)->many()->where('this.uuid', \array_values($primaryKeys))->update([$targetKey => null]);
	}
	
	/**
	 * Unrelate all records
	 * Collection will be cleared before relate
	 */
	public function unrelateAll(): int
	{
		if ($this->isLoaded()) {
			$this->clear();
		}
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaSourceKey = $this->relation->getSourceViaKey();
			
			return $this->getConnection()->rows()->setFrom(['this' => $via])->setWhere("this.$viaSourceKey", $this->keyValue)->delete();
		}
		
		// RelationNx1
		$class = $this->relation->getTarget();
		$sourceKey = $this->relation->getSourceKey();
		$targetKey = $this->relation->getTargetKey();
		
		return $this->getRepository()->getConnection()->findRepository($class)->many()->setWhere("this.$sourceKey", $this->keyValue)->update([$targetKey => null]);
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
		return parent::setSelect($select, $values, $keepIndex);
	}
	
	/**
	 * @param bool $needed
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function first(bool $needed = false): ?Entity
	{
		return parent::first($needed);
	}
	
	public function fetch(): ?Entity
	{
		return parent::fetch();
	}
	
	protected function init(): void
	{
		parent::init();
		
		if ($this->relation instanceof RelationNxN) {
			$via = $this->relation->getVia();
			$viaTargetKey = $this->relation->getTargetViaKey();
			$viaSourceKey = $this->relation->getSourceViaKey();
			$targetKey = $this->relation->getTargetKey();
			$this->setJoin(['via' => $via], "via.$viaTargetKey=this.$targetKey");
			$this->setWhere("via.$viaSourceKey", $this->keyValue);
		} else {
			$this->setWhere($this->relation->getTargetKey(), [$this->keyValue]);
		}
		
		return;
	}
}
