<?php

declare(strict_types=1);

namespace StORM;

/**
 * Interface ICollection
 * @template T of object
 */
interface ICollection
{
	/**
	 * Get current connection
	 * @return \StORM\Connection $connection
	 */
	public function getConnection(): Connection;
	
	/**
	 * Get array of modifiers: WHERE, FROM, SELECT, LIMIT, OFFSET, ORDER BY, GROUP BY, HAVING BY, JOIN
	 * @return string[]
	 */
	public function getModifiers(): array;
	
	/**
	 * Set fetch class or class parameters
	 * @param string|null $class
	 * @param mixed[]|null $params
	 */
	public function setFetchClass(?string $class, ?array $params = []): self;
	
	/**
	 * Get fetch class
	 * @param mixed[] $params
	 * @phpstan-var class-string<T> $params
	 */
	public function getFetchClass(array &$params = []): string;
	
	/**
	 * Fetch all collection and fill keys
	 * @phpstan-return \StORM\ICollection<T>
	 */
	public function load(): ICollection;
	
	/**
	 * Tells if collection is fetched
	 */
	public function isLoaded(): bool;
	
	/**
	 * Take 1, fetch column name or first column if null
	 * @param string|null $property
	 * @param bool $needed
	 * @return null|string|bool
	 */
	public function firstValue(?string $property = null, bool $needed = false);
	
	/**
	 * Take 1, fetch and close cursor, if property is not null fetch the property
	 * @param bool $needed
	 * @phpstan-return T|null
	 */
	public function first(bool $needed = false): ?object;
	
	/**
	 * Fetch object and move cursor
	 * @phpstan-return T|null
	 */
	public function fetch(): ?object;
	
	/**
	 * Get PDO statement handle. Ff its not created, it will be
	 */
	public function getPDOStatement(): \PDOStatement;
	
	/**
	 * Delete all record equals condition and return number of affected rows
	 */
	public function delete(): int;
	
	/**
	 * Update all record equals condition and return number of affected rows
	 * @param string[]|null[] $values
	 * @param bool $ignore
	 * @param string|null $alias
	 */
	public function update(array $values, bool $ignore = false, ?string $alias = null): int;
	
	/**
	 * Get sql SELECT string
	 */
	public function getSql(): string;
	
	/**
	 * Return array of parsed vars, means converted from Entity to primary key, from literal to SQL string
	 * @param int|null $flags
	 * @return mixed[]
	 */
	public function getVars(?int $flags = null): array;
	
	/**
	 * Get sql DELETE string
	 */
	public function getSqlDelete(): string;
	
	/**
	 * Get sql string for sql UPDATE records and bind variables in updates
	 * @param mixed[] $updates
	 * @param bool $ignore
	 * @param string|null $alias
	 */
	public function getSqlUpdate(array &$updates, bool $ignore = false, ?string $alias = null): string;
	
	/**
	 * Clear all data in collection, can clear modifiers also
	 * @param bool $clearModifiers
	 * @return static
	 */
	public function clear(bool $clearModifiers = false): self;
	
	/**
	 * Convert collection to array of object
	 * @phpstan-return T[]
	 * @return object[]
	 */
	public function toArray(): array;
	
	/**
	 * Convert collection to array of trings
	 * @param string $columnOrExpression
	 * @param string[] $callbacks or $columns
	 * @param bool $toArrayValues
	 * @phpstan-return mixed[]
	 * @return mixed[]
	 */
	public function toArrayOf(string $columnOrExpression, array $callbacks = [], bool $toArrayValues = false): array;
	
	/**
	 * Set collection index of internal array
	 * @param string|null $index
	 * @param bool $prefixIndex
	 */
	public function setIndex(?string $index, bool $prefixIndex = true): ICollection;
	
	/**
	 * Count internal array if loaded, otherwise call enum()
	 */
	public function count(): int;
	
	public function isEmpty(): bool;
	
	/**
	 * Call COUNT($column)
	 * @param string|null $column
	 * @param bool $unique
	 */
	public function enum(?string $column = null, bool $unique = true): int;
	
	/**
	 * Call SUM($column)
	 * @param string $column
	 */
	public function sum(string $column): float;
	
	/**
	 * Call AVG($column)
	 * @param string $column
	 */
	public function avg(string $column): float;
	
	/**
	 * Call MIN($column)
	 * @param string $column
	 */
	public function min(string $column): float;
	
	/**
	 * Call MAX($column)
	 * @param string $column
	 */
	public function max(string $column): float;
	
	/**
	 * Call sql function on args and return raw value
	 * @param string $function
	 * @param string[] $args
	 */
	public function func(string $function, array $args): string;
	
	/**
	 * Set WHERE condition and replace previous
	 * @param string $expression
	 * @param mixed[]|null|mixed $values
	 * @return static
	 */
	public function setWhere(?string $expression, $values = null): self;
	
	/**
	 * Add WHERE condition with "AND" glue
	 * @param string $expression
	 * @param mixed[]|null|mixed $values
	 * @return static
	 */
	public function where(string $expression, $values = null): self;
	
	/**
	 * Call multiple where
	 * @param mixed[] $conditions
	 * @param string $columnPrefix
	 * @return static
	 */
	public function match(array $conditions, string $columnPrefix = ''): self;
	
	/**
	 * Set WHERE negated condition and replace previous
	 * @param string $expression
	 * @param mixed[]|null|mixed $values
	 * @return static
	 */
	public function setWhereNot(string $expression, $values = null): self;
	
	/**
	 * Add WHERE negated condition with "AND" glue
	 * @param string $expression
	 * @param mixed[]|null|mixed $values
	 * @return static
	 */
	public function whereNot(string $expression, $values = null): self;
	
	/**
	 * Set FROM clause and remove previous
	 * @param string[]|\StORM\ICollection[] $from
	 * @param mixed[] $values
	 * @return static
	 */
	public function setFrom(array $from, array $values = []): self;
	
	/**
	 * Add FROM clause and merge with previous
	 * @param string[]|\StORM\ICollection[] $from
	 * @param mixed[] $values
	 * @return static
	 */
	public function from(array $from, array $values = []): self;
	
	/**
	 * Returns prefix for columns expressions, like "this."
	 */
	public function getPrefix(bool $dot = true): ?string;
	
	/**
	 * Set SELECT clause and replace previous
	 * @param string[] $select
	 * @param mixed[] $values
	 * @param bool $keepIndex
	 * @return static
	 */
	public function setSelect(array $select, array $values = [], bool $keepIndex = false): self;
	
	/**
	 * Add SELECT clause and merge with previous
	 * @param string[] $select
	 * @param mixed[] $values
	 * @return static
	 */
	public function select(array $select, array $values = []): self;
	
	/**
	 * Add LIMIT clause
	 * @param int|null $number
	 * @return static
	 */
	public function setTake(?int $number): self;
	
	/**
	 * Add OFFSET clause
	 * @param int|null $number
	 * @return static
	 */
	public function setSkip(?int $number): self;
	
	/**
	 * Combine skip() and take() to slice page you want
	 * @param int $page
	 * @param int $onPage
	 * @return static
	 */
	public function setPage(int $page, int $onPage): self;
	
	/**
	 * Set ORDER clause and replace previous
	 * @param string[] $order
	 * @param mixed[] $values
	 * @return static
	 */
	public function setOrderBy(array $order, array $values = []): self;
	
	/**
	 * Add ORDER clause and merge with previous
	 * @param string[] $order
	 * @param mixed[] $values
	 * @return static
	 */
	public function orderBy(array $order, array $values = []): self;
	
	/**
	 * Set GROUP BY and HAVING clause and replace previous
	 * @param string[] $groups
	 * @param null|string $having
	 * @param mixed[] $values
	 * @return static
	 */
	public function setGroupBy(array $groups, ?string $having = null, array $values = []): self;
	
	/**
	 * Set GROUP BY for all columns excepts columns in parameter $exceptColumns and HAVING clause and replace previous
	 * @param string[] $exceptColumns
	 * @param null|string $having
	 * @return static
	 */
	public function setFullGroupBy(array $exceptColumns, ?string $having = null): self;
	
	/**
	 * Set JOIN clause and replace previous
	 * @param string[] $from
	 * @param string $condition
	 * @param mixed[] $values
	 * @param string|null $type
	 * @return static
	 */
	public function setJoin(array $from, ?string $condition = null, array $values = [], ?string $type = null): self;
	
	/**
	 * Add JOIN clause and merge with previous
	 * @param string[]|\StORM\ICollection[] $from
	 * @param string $condition
	 * @param mixed[] $values
	 * @param string|null $type
	 * @return static
	 */
	public function join(array $from, string $condition, array $values = [], ?string $type = null): self;
	
	/**
	 * Get last affected number
	 */
	public function getAffectedNumber(): ?int;
	
	public function setAffectedNumber(?int $affectedNumber): void;
	
	/**
	 * Get possible values of column based by WHERE column IN ($possibleValues)
	 * @param string $column
	 * @return string[]
	 */
	public function getPossibleValues(string $column): array;
	
	/**
	 * Specify data which should be serialized to JSON
	 * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize();
	
	/**
	 * Get real SQL string
	 */
	public function __toString(): string;
}
