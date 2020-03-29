<?php

namespace StORM;

interface ICollectionRelation extends ICollectionEntity
{
	/**
	 * Relate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param mixed[] $primaryKeys
	 * @param bool $checkKeys
	 * @param string|null $primaryKeyName You can specify column name and method will generate primary keys for that columns
	 * @return int
	 */
	public function relate(array $primaryKeys, bool $checkKeys = true, ?string $primaryKeyName = null): int;
	
	/**
	 * Unrelate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param mixed[] $primaryKeys
	 * @return int
	 */
	public function unrelate(array $primaryKeys): int;
	
	/**
	 * Unrelate all records
	 * Collection will be cleared before relate
	 * @return int
	 */
	public function unrelateAll(): int;
}
