<?php

declare(strict_types=1);

namespace StORM;

interface ICollectionEntity extends ICollection
{
	/**
	 * Get collection repository
	 * @return \StORM\Repository
	 */
	public function getRepository(): Repository;
	
	/**
	 * Call user filters on repository
	 * @param mixed[][] $filters
	 * @param bool $silent
	 * @return \StORM\ICollectionEntity
	 */
	public function filter(array $filters, bool $silent = false): ICollectionEntity;
	
	/**
	 * Get possible values of column based by WHERE column IN ($possibleValues)
	 * @override adding default alias to search in array
	 * @param string $column
	 * @return string[]
	 */
	public function getPossibleValues(string $column): array;
	
	/**
	 * Update all record equals condition and return number of affected rows
	 * @override adding filter by columns
	 * @param string[] $values
	 * @param bool $ignore
	 * @param bool|null $filterByColumns
	 * @return int
	 */
	public function update(array $values, bool $ignore = false, ?bool $filterByColumns = null): int;
}
