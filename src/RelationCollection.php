<?php

declare(strict_types = 1);

namespace StORM;

use StORM\Exception\InvalidStateException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Relation;
use StORM\Meta\RelationNxN;

/**
 * Class CollectionRelation
 * @template T of \StORM\Entity
 * @extends \StORM\Collection<T>
 * @implements \ArrayAccess<string|int, T>
 * @implements \Iterator<string|int, T>
 * @implements \StORM\ICollection<T>
 */
class RelationCollection extends Collection implements IRelation, ICollection, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	protected \StORM\Meta\Relation $relation;
	
	protected string $keyValue;
	
	/**
	 * CollectionRelation constructor.
	 * @param \StORM\Repository<T> $repository
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
		
		// @phpstan-ignore-next-line
		parent::__construct($repository->getConnection()->findRepository($relation->getTarget()));
	}
	
	/**
	 * Relate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param array<string|int>|array<array<string|int>> $primaryKeys
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
			$sth->closeCursor();
			
			return $count;
		}
		
		// RelationNx1

		$class = $this->relation->getTarget();
		$targetKey = $this->relation->getTargetKey();
	
		$collection = $this->getRepository()->getConnection()->findRepository($class)->many()->setWhere('this.uuid', \array_values($primaryKeys));

		if ($checkKeys) {
			$pkName = $this->getRepository()->getConnection()->findRepository($class)->getStructure()->getPK()->getName();
			$resultArray = (clone $collection)->toArrayOf($pkName);
			
			foreach ($primaryKeys as $value) {
				if (\is_Array($value)) {
					throw new \InvalidArgumentException('Cannot pass multidimensional array if relation is not NxN');
				}
			}
			
			// @phpcs:ignore
			/** @var array<string|int> $primaryKeys */
			$desiredArray = \array_combine(\array_values($primaryKeys), \array_values($primaryKeys));
			
			if ($desiredArray !== false && $diff = \array_diff_key($desiredArray, $resultArray)) {
				throw new NotFoundException($this, $diff, $desiredArray);
			}
		}
		
		return $collection->update([$targetKey => $this->keyValue]);
	}
	
	/**
	 * Unrelate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param array<string>|array<int> $primaryKeys
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
	}
}
