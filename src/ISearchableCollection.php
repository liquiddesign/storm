<?php

declare(strict_types = 1);

namespace StORM;

/**
 * Interface ISearchableCollectionISearchableCollection
 * @template T of object
 */
interface ISearchableCollection
{
	/**
	 * Fetch all collection and fill keys
	 * @phpstan-return \StORM\ISearchableCollection<T>
	 */
	public function load(): ISearchableCollection;

	/**
	 * Tells if collection is fetched
	 */
	public function isLoaded(): bool;

	/**
	 * Take 1, fetch column name or first column if null
	 * @param string|null $property
	 * @param bool $needed
	 * @param string|null $columnName
	 * @return null|string|bool
	 */
	public function firstValue(?string $property = null, bool $needed = false, ?string $columnName = null);

	/**
	 * Take 1, fetch and close cursor, if property is not null fetch the property
	 * @param bool $needed
	 * @param string|null $columnName
	 * @param bool $load
	 * @phpstan-return T|null
	 */
	public function first(bool $needed = false, ?string $columnName = null, bool $load = false): ?object;

	/**
	 * @param string|null $columnName
	 * @param bool $needed
	 * @param bool $load
	 * @return T|null
	 */
	public function last(?string $columnName = null, bool $needed = false, bool $load = false): ?object;

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
	 * Get sql SELECT string
	 */
	public function getSql(): string;

	/**
	 * Return array of parsed vars, means converted from Entity to primary key, from literal to SQL string
	 * @param int|null $flags
	 * @return array<mixed>
	 */
	public function getVars(?int $flags = null): array;

	/**
	 * Convert collection to array of object
	 * @param bool $toArrayValues
	 * @phpstan-return array<T>
	 * @return array<object>
	 */
	public function toArray(bool $toArrayValues = false): array;

	/**
	 * Convert collection to array of trings
	 * @param string $columnOrExpression
	 * @param array<string>|array<callable> $callbacks or $columns
	 * @param bool $toArrayValues
	 * @phpstan-return array<mixed>
	 * @return array<mixed>
	 */
	public function toArrayOf(string $columnOrExpression, array $callbacks = [], bool $toArrayValues = false): array;

	/**
	 * Fetch as array of class types $class
	 * @template X
	 * @param class-string<X> $class
	 * @param array<mixed> $classArgs
	 * @param bool $toArrayValues
	 * @return array<X>
	 */
	public function fetchArray(string $class, array $classArgs = [], bool $toArrayValues = false): array;

	/**
	 * Fetch columns into array
	 * @return array<string>
	 */
	public function fetchColumns(string $column, bool $toArrayValues = false): array;

	/**
	 * Set collection index of internal array
	 * @param string|null $index
	 * @param bool $prefixIndex
	 * @return static
	 */
	public function setIndex(?string $index, bool $prefixIndex = true): self;

	/**
	 * Set WHERE condition and replace previous
	 * @param string|null $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhere(?string $expression, $values = null): self;

	/**
	 * Add WHERE condition with "AND" glue
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function where(string $expression, $values = null): self;

	/**
	 * Call multiple where
	 * @param array<mixed> $conditions
	 * @param string $columnPrefix
	 * @return static
	 */
	public function whereMatch(array $conditions, string $columnPrefix = ''): self;

	/**
	 * @param string $expression
	 * @param string|int|float|null $from
	 * @param string|int|float|null $to
	 * @param bool $fromEquals
	 * @param bool $toEquals
	 * @return static
	 */
	public function whereBetween(string $expression, $from = null, $to = null, bool $fromEquals = true, bool $toEquals = true): self;

	/**
	 * Set WHERE negated condition and replace previous
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhereNot(string $expression, $values = null): self;

	/**
	 * Add WHERE negated condition with "AND" glue
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function whereNot(string $expression, $values = null): self;

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
	 * @param array<string> $order
	 * @param array<mixed> $values
	 * @return static
	 */
	public function setOrderBy(array $order, array $values = []): self;

	/**
	 * Add ORDER clause and merge with previous
	 * @param array<string> $order
	 * @param array<mixed> $values
	 * @return static
	 */
	public function orderBy(array $order, array $values = []): self;

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
