<?php

declare(strict_types=1);

namespace StORM;

interface IRelation
{
	/**
	 * Relate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param array<mixed> $primaryKeys
	 * @param bool $checkKeys
	 * @param string|null $primaryKeyName You can specify column name and method will generate primary keys for that columns
	 */
	public function relate(array $primaryKeys, bool $checkKeys = true, ?string $primaryKeyName = null): int;
	
	/**
	 * Unrelate records by primary key lists and return affected rows
	 * Collection will be cleared before relate
	 * @param array<mixed> $primaryKeys
	 */
	public function unrelate(array $primaryKeys): int;
	
	/**
	 * Unrelate all records
	 * Collection will be cleared before relate
	 */
	public function unrelateAll(): int;
}
