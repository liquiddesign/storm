<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\Exception\AlreadyExistsException;
use StORM\Exception\GeneralException;
use StORM\Exception\InvalidStateException;
use StORM\Exception\NotFoundException;

/**
 * Class Collection
 * @template T of object
 * @implements \ArrayAccess<string|int, T>
 * @implements \Iterator<string|int, T>
 * @implements \StORM\ICollection<T>
 */
class GenericCollection implements ICollection, IDumper, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	protected const DEFAULT_JOIN = 'LEFT';
	
	protected const BINDER_NAME = '__var';
	
	protected const UNIQUE_BINDER_PREFIX = '__';
	
	/**
	 * According to MySQL
	 */
	protected const MAX_LIMIT = '18446744073709551615';
	
	protected const MODIFIER_WHERE = 'WHERE';
	
	protected const MODIFIER_FROM = 'FROM';
	
	protected const MODIFIER_SELECT = 'SELECT';
	
	protected const MODIFIER_LIMIT = 'LIMIT';
	
	protected const MODIFIER_OFFSET = 'OFFSET';
	
	protected const MODIFIER_ORDER_BY = 'ORDER BY';
	
	protected const MODIFIER_GROUP_BY = 'GROUP BY';
	
	protected const MODIFIER_HAVING = 'HAVING';
	
	protected const MODIFIER_JOIN = 'JOIN';
	
	protected const MODIFIER_SELECT_FLAG = 1;
	
	protected const MODIFIER_FROM_FLAG = 2;
	
	protected const MODIFIER_JOIN_FLAG = 4;
	
	protected const MODIFIER_WHERE_FLAG = 8;
	
	protected const MODIFIER_ORDER_BY_FLAG = 16;
	
	protected const MODIFIER_GROUP_BY_FLAG = 32;
	
	protected const REGEXP_AUTOJOIN = '/(?:[A-Za-z0-9_]+\.)+/';
	
	protected const ITERATOR_WILDCARD = '__iterator';
	
	protected const ORDER_ASC = 'ASC';
	
	protected const ORDER_DESC = 'DESC';
	
	/**
	 * @var array<T>|null
	 */
	protected ?array $items = null;
	
	/**
	 * @var array<string|int>
	 */
	protected array $keys;
	
	/**
	 * @var class-string<T>
	 */
	protected string $class;
	
	/**
	 * @var array<mixed>
	 */
	protected array $classArguments = [];
	
	protected ?\PDOStatement $sth = null;
	
	protected int $binderCounter = 0;
	
	/**
	 * @var array<string>
	 */
	protected array $aliases = [];
	
	/**
	 * @var array<string>
	 */
	protected array $tableAliases = [];
	
	/**
	 * @var array<mixed>
	 */
	protected array $modifiers = [];
	
	/**
	 * @var array<string>
	 */
	protected ?array $baseFrom;
	
	/**
	 * @var array<string>
	 */
	protected array $baseSelect;
	
	protected ?string $index;
	
	protected bool $prefixIndex = true;
	
	/**
	 * @var array<mixed>
	 */
	protected array $vars = [];
	
	/**
	 * @var array<int>
	 */
	protected array $varsFlags = [];

	protected ?\StORM\Connection $connection;
	
	protected ?int $affectedNumber = null;
	
	/**
	 * @var array<array<string>>
	 */
	protected array $possibleValues = [];
	
	protected ?bool $bufferedQuery = null;
	
	protected string $binderName;
	
	/**
	 * Rows constructor.
	 * @phpstan-param class-string<T> $class
	 * @param \StORM\Connection $connection
	 * @param array<string> $from
	 * @param array<string> $select
	 * @param string $class
	 * @param array<mixed> $classArguments
	 * @param string|null $index
	 */
	public function __construct(Connection $connection, ?array $from, array $select, string $class, array $classArguments = [], ?string $index = null)
	{
		$this->connection = $connection;
		$this->class = $class;
		$this->index = $index;
		$this->baseFrom = $from;
		$this->baseSelect = $select;
		$this->classArguments = $classArguments;
		$this->binderName = self::BINDER_NAME . \spl_object_id($this);
		
		$this->init();
	}
	
	/**
	 * Get current connection
	 * @return \StORM\Connection $connection
	 */
	public function getConnection(): Connection
	{
		if (!$this->connection) {
			throw new InvalidStateException($this, InvalidStateException::CONNECTION_NOT_SET);
		}
		
		return $this->connection;
	}
	
	public function setConnection(Connection $connection): void
	{
		$this->connection = $connection;
	}
	
	/**
	 * Get array of modifiers: WHERE, FROM, SELECT, LIMIT, OFFSET, ORDER BY, GROUP BY, HAVING BY, JOIN
	 * @return array<mixed>
	 */
	public function getModifiers(): array
	{
		return $this->modifiers;
	}
	
	/**
	 * @param bool|null $bufferedQuery
	 * @return static
	 */
	public function setBufferedQuery(?bool $bufferedQuery): self
	{
		$this->bufferedQuery = $bufferedQuery;
		
		return $this;
	}
	
	/**
	 * Set fetch class or class parameters
	 * @param class-string<object>|null $class
	 * @param array<mixed>|null $params
	 * @return static
	 */
	public function setFetchClass(?string $class, ?array $params = []): self
	{
		if ($class !== null) {
			$this->class = $class;
		}
		
		if ($params !== null) {
			$this->classArguments = $params;
		}
		
		return $this;
	}
	
	/**
	 * Get fetch class
	 * @param array<mixed> $params
	 * @phpstan-return class-string<T>
	 */
	public function getFetchClass(array &$params = []): string
	{
		$params = $this->classArguments;
		
		return $this->class;
	}
	
	/**
	 * Set last affected number
	 * @param int|null $affectedNumber
	 * @internal
	 */
	public function setAffectedNumber(?int $affectedNumber): void
	{
		$this->affectedNumber = $affectedNumber;
	}
	
	/**
	 * Get last affected number
	 */
	public function getAffectedNumber(): ?int
	{
		return $this->affectedNumber;
	}
	
	/**
	 * Get possible values of column based by WHERE column IN ($possibleValues)
	 * @param string $column
	 * @return array<string>
	 */
	public function getPossibleValues(string $column): array
	{
		return $this->possibleValues[$column] ?? [];
	}
	
	/**
	 * Load all items and fill keys
	 * @return static
	 */
	public function load(bool $forceLoad = true): self
	{
		if (!$forceLoad && $this->isLoaded()) {
			return $this;
		}
		
		$this->items = \Nette\Utils\Helpers::falseToNull($this->getPDOStatement()->fetchAll(...$this->getFetchParameters()));
		
		if ($this->items === null) {
			throw new GeneralException('Load collection failed:' . \implode(':', $this->getConnection()->getLink()->errorInfo()));
		}
		
		$this->keys = \array_keys($this->items);
		$this->affectedNumber = null;
		
		return $this;
	}
	
	/**
	 * Tells if collection is fetched
	 */
	public function isLoaded(): bool
	{
		return $this->items !== null;
	}
	
	/**
	 * Take 1, fetch fetch the column and close cursor
	 * @param string|null $property
	 * @param bool $needed
	 * @return null|string|int|bool
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function firstValue(?string $property = null, bool $needed = false, ?string $columnName = null)
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		return $this->getValue($property, $needed, function (ICollection $collection) use ($columnName): void {
			$collection->setTake(1);
			
			if (!$columnName) {
				return;
			}

			$collection->setOrderBy([$columnName => self::ORDER_ASC]);
		});
	}
	
	/**
	 * Take 1, fetch fetch the last column and close cursor
	 * @param string|null $property
	 * @param bool $needed
	 * @return null|string|int|bool
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function lastValue(?string $property = null, ?string $columnName = null, bool $needed = false)
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		if (!$columnName) {
			throw new \InvalidArgumentException('Column cannot be empty');
		}
	
		return $this->getValue($property, $needed, function (ICollection $collection) use ($columnName): void {
			$collection->setOrderBy([$columnName => self::ORDER_DESC]);
			$collection->setTake(1);
		});
	}
	
	/**
	 * Take 1, fetch and close cursor, if property is not null fetch the property
	 * @param bool $needed
	 * @param string|null $columnName
	 * @param bool $load
	 * @throws \StORM\Exception\NotFoundException
	 * @return T|null
	 */
	public function first(bool $needed = false, ?string $columnName = null, bool $load = false): ?object
	{
		if ($load) {
			$this->load(false);
		}
		
		if ($this->isLoaded() && $columnName === null) {
			return Arrays::first($this->getItems());
		}
		
		return $this->fetchCloned($needed, function (ICollection $collection) use ($columnName): void {
			$collection->setTake(1);
			
			if (!$columnName) {
				return;
			}

			$collection->setOrderBy([$columnName => self::ORDER_ASC]);
		});
	}
	
	/**
	 * Take 1, fetch and close cursor, if property is not null fetch the property
	 * @param string|null $columnName
	 * @param bool $needed
	 * @param bool $load
	 * @return T|null
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function last(?string $columnName = null, bool $needed = false, bool $load = false): ?object
	{
		if ($load) {
			$this->load(false);
		}
		
		if ($this->isLoaded() && $columnName === null) {
			return Arrays::last($this->getItems());
		}
		
		if (!$columnName) {
			throw new \InvalidArgumentException('Column name is empty and collection is not loaded. Pass 3rd argument "true"');
		}
		
		return $this->fetchCloned($needed, function (ICollection $collection) use ($columnName): void {
			$collection->setOrderBy([$columnName => self::ORDER_DESC]);
			$collection->setTake(1);
		});
	}
	
	/**
	 * Fetch object and move cursor
	 * @return T|null
	 */
	public function fetch(): ?object
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		return \Nette\Utils\Helpers::falseToNull($this->getPDOStatement()->fetch());
	}
	
	/**
	 * Delete all record equals condition and return number of affected rows
	 */
	public function delete(): int
	{
		$flags = self::MODIFIER_FROM_FLAG | self::MODIFIER_JOIN_FLAG | self::MODIFIER_WHERE_FLAG;
		
		$sth = $this->getConnection()->query($this->getSqlDelete(), $this->getVars($flags));
		$this->affectedNumber = $sth->rowCount();
		
		return $this->affectedNumber;
	}
	
	/**
	 * Update all record equals condition and return number of affected rows
	 * @param array<mixed>|object $values
	 * @param bool $ignore
	 * @param string|null $alias
	 */
	public function update($values, bool $ignore = false, ?string $alias = null): int
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		if (\count($values) === 0) {
			throw new \InvalidArgumentException('No value to update');
		}
		
		$sql = $this->getSqlUpdate($values, $ignore, $alias);
		
		$flags = self::MODIFIER_FROM_FLAG | self::MODIFIER_JOIN_FLAG | self::MODIFIER_WHERE_FLAG | self::MODIFIER_ORDER_BY_FLAG;
	
		$sth = $this->getConnection()->query($sql, $this->getVars($flags) + $values);
		$this->affectedNumber = $sth->rowCount();
		
		return $this->affectedNumber;
	}
	
	/**
	 * Get sql SELECT string
	 */
	public function getSql(): string
	{
		$indexSelect = $this->index ? [$this->modifiers[self::MODIFIER_SELECT][$this->index] ?? $this->index] : [];
		
		if ($indexSelect && isset($this->modifiers[self::MODIFIER_SELECT][0]) && $this->modifiers[self::MODIFIER_SELECT][0] === '*') {
			if (\count($this->modifiers[self::MODIFIER_FROM]) > 1) {
				throw new InvalidStateException($this, InvalidStateException::INDEX_AND_STAR_WITHOUT_PREFIX, $this->index);
			}
			
			$this->modifiers[self::MODIFIER_SELECT][0] = $this->getPrefix() . $this->modifiers[self::MODIFIER_SELECT][0];
		}
		
		$sql = $this->createSqlPrefix(true, true, $this->prefixIndex ? $indexSelect : []);
		$sql .= $this->createSqlSuffix(true, true);
		
		return $this->replaceLiterals($sql, $this->vars);
	}
	
	/**
	 * Get sql DELETE string
	 */
	public function getSqlDelete(): string
	{
		if ($this->modifiers[self::MODIFIER_GROUP_BY]) {
			throw new InvalidStateException($this, InvalidStateException::GROUP_BY_NOT_ALLOWED);
		}
		
		if ($this->modifiers[self::MODIFIER_ORDER_BY]) {
			throw new InvalidStateException($this, InvalidStateException::ORDER_BY_NOT_ALLOWED);
		}
		
		$alias = $this->getPrefix(false);
		
		$sql = "DELETE $alias ";
		$sql .= $this->createSqlPrefix(false, true);
		$sql .= $this->createSqlSuffix(false, false);
		
		return $this->replaceLiterals($sql, $this->vars);
	}
	
	/**
	 * Get sql string for sql UPDATE records and bind variables in updates
	 * @param array<mixed> $updates
	 * @param bool $ignore
	 * @param string|null $alias
	 */
	public function getSqlUpdate(array &$updates, bool $ignore = false, ?string $alias = null): string
	{
		if ($this->modifiers[self::MODIFIER_GROUP_BY]) {
			throw new InvalidStateException($this, InvalidStateException::GROUP_BY_NOT_ALLOWED);
		}
		
		$values = $binds = [];
		$varPrefix = self::UNIQUE_BINDER_PREFIX;
		
		foreach ($updates as $property => $rawValue) {
			$this->getConnection()->bindVariables($property, $rawValue, $values, $binds, $varPrefix, '', $alias === null ? '' : "$alias.");
		}
		
		$updates = $values;
		$flags = $ignore ? ' IGNORE' : '';
		
		$sql = Helpers::createSqlClauseString("UPDATE$flags", $this->modifiers[self::MODIFIER_FROM], ',', ' AS ');
		$sql .= $this->createSqlPrefix(false, false);
		$sql .= Helpers::createSqlClauseString(' SET', $binds, ',', '=') . ' ';
		$sql .= $this->createSqlSuffix(false, true);
		
		return $this->replaceLiterals($sql, $updates + $this->vars);
	}
	
	/**
	 * Clear all data in collection, can clear modifiers also
	 * @param bool $clearModifiers
	 * @return static
	 */
	public function clear(bool $clearModifiers = false): self
	{
		$this->sth = null;
		$this->items = null;
		
		if ($clearModifiers) {
			$this->init();
		}
		
		return $this;
	}
	
	/**
	 * Convert collection to array of object
	 * @param bool $toArrayValues
	 * @phpstan-return array<T>
	 * @return array<object>
	 */
	public function toArray(bool $toArrayValues = false): array
	{
		return $toArrayValues ? \array_values($this->getItems()) : $this->getItems();
	}
	
	/**
	 * Convert collection to array of strings
	 * @param string $columnOrExpression
	 * @param array<string>|array<callable> $callbacks or $columns
	 * @param bool $toArrayValues
	 * @return array<mixed>
	 */
	public function toArrayOf(string $columnOrExpression, array $callbacks = [], bool $toArrayValues = false): array
	{
		if (Strings::contains($columnOrExpression, '%')) {
			$return = $this->format($columnOrExpression, $callbacks);
		} else {
			$return = [];
			
			foreach ($this->getItems() as $index => $value) {
				$return[$index] = $value->$columnOrExpression;
			}
		}
		
		return $toArrayValues ? \array_values($return) : $return;
	}
	
	/**
	 * Call array map on collection
	 * @param callable $callback
	 * @param bool $toArrayValues
	 * @return array<mixed>
	 */
	public function map(callable $callback, bool $toArrayValues = false): array
	{
		return \array_map($callback, $this->toArray($toArrayValues));
	}
	
	/**
	 * Create grouped array indexed by property (using PDO::FETCH_GROUP)
	 * @param string $property
	 * @phpstan-return array<array<T>>
	 * @return array<array<object>>
	 */
	public function getGroups(string $property): array
	{
		$collection = clone $this;
		$collection->setIndex($property);
		$sth = $this->getConnection()->query($collection->getSql(), $collection->getVars(), [], $this->bufferedQuery);
		
		$result = \Nette\Utils\Helpers::falseToNull($sth->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_GROUP, $this->class, $this->classArguments));
		
		if ($result === null) {
			throw new GeneralException('Fetch collection failed:' . \implode(':', $this->getConnection()->getLink()->errorInfo()));
		}
		
		return $result;
	}
	
	/**
	 * Set collection index of internal array
	 * @param string|null $index
	 * @param bool $prefixIndex
	 * @return static
	 */
	public function setIndex(?string $index, bool $prefixIndex = true): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->index = $index;
		$this->prefixIndex = $prefixIndex;
		
		return $this;
	}
	
	/**
	 * Count internal array if loaded, otherwise call enum()
	 */
	public function count(): int
	{
		return \count($this->getItems());
	}
	
	/**
	 * Call COUNT($column)
	 * @param string|null $column
	 * @param bool $unique
	 */
	public function enum(?string $column = null, bool $unique = true): int
	{
		$distinct = $unique ? 'DISTINCT ' : '';
		
		return (int) $this->func('COUNT', [$column ? "$distinct$column" : '*']);
	}
	
	public function isEmpty(): bool
	{
		return !$this->count();
	}
	
	/**
	 * Call SUM($column)
	 * @param string $column
	 */
	public function sum(string $column): float
	{
		return (float) $this->func('SUM', [$column]);
	}
	
	/**
	 * Call AVG($column)
	 * @param string $column
	 */
	public function avg(string $column): float
	{
		return (float) $this->func('AVG', [$column]);
	}
	
	/**
	 * Call MIN($column)
	 * @param string $column
	 */
	public function min(string $column): float
	{
		return (float) $this->func('MIN', [$column]);
	}
	
	/**
	 * Call MAX($column)
	 * @param string $column
	 */
	public function max(string $column): float
	{
		return (float) $this->func('MAX', [$column]);
	}
	
	/**
	 * Call sql function on args and return raw value
	 * @param string $function
	 * @param array<string> $args
	 */
	public function func(string $function, array $args): string
	{
		$select = [$function . '(' . \implode(',', $args) . ')'];
		$clone = clone $this;
		
		$clone->clear();
		$clone->setSelect($select);
		
		return (string) $clone->getPDOStatement()->fetchColumn();
	}
	
	/**
	 * Set WHERE condition and replace previous
	 * @param string|null $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhere(?string $expression, $values = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		if ($expression === null) {
			$this->modifiers[self::MODIFIER_WHERE] = [];
		} else {
			$this->processWhere($expression, $values, false, true);
		}
		
		return $this;
	}
	
	/**
	 * Add WHERE condition with "AND" glue
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function where(string $expression, $values = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->processWhere($expression, $values, false, false);
		
		return $this;
	}
	
	/**
	 * Call multiple where
	 * @param array<mixed> $conditions
	 * @param string $columnPrefix
	 * @return static
	 */
	public function whereMatch(array $conditions, string $columnPrefix = ''): self
	{
		foreach ($conditions as $property => $value) {
			$this->where($columnPrefix . $property, $value);
		}
		
		return $this;
	}
	
	/**
	 * Call multiple where
	 * @deprecated use whereMatch
	 * @param array<mixed> $conditions
	 * @param string $columnPrefix
	 * @return static
	 */
	public function match(array $conditions, string $columnPrefix = ''): self
	{
		return $this->whereMatch($conditions, $columnPrefix);
	}
	
	/**
	 * Set WHERE negated condition and replace previous
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhereNot(string $expression, $values = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->processWhere($expression, $values, true, true);
		
		return $this;
	}
	
	/**
	 * Add WHERE negated condition with "AND" glue
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function whereNot(string $expression, $values = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->processWhere($expression, $values, true, false);
		
		return $this;
	}
	
	/**
	 * @param string $expression
	 * @param string|int|float|null $from
	 * @param string|int|float|null $to
	 * @param bool $fromEquals
	 * @param bool $toEquals
	 * @return static
	 */
	public function whereBetween(string $expression, $from = null, $to = null, bool $fromEquals = true, bool $toEquals = true): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		if ($from) {
			$mark = $fromEquals ? '>=' : '>';
			$binder = $this->generateBinder();
			$this->processWhere("$expression $mark :$binder", [$binder => $from], false, false);
		}
		
		if ($to) {
			$mark = $toEquals ? '<=' : '<';
			$binder = $this->generateBinder();
			$this->processWhere("$expression $mark :$binder", [$binder => $to], false, false);
		}
		
		return $this;
	}
	
	/**
	 * Add FROM clause and merge with previous
	 * @param array<string>|array<\StORM\ICollection<T>> $from
	 * @param array<mixed> $values
	 * @return static
	 */
	public function from(array $from, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->addAlias($from, self::MODIFIER_FROM);
		
		$this->modifiers[self::MODIFIER_FROM] = \array_merge($this->modifiers[self::MODIFIER_FROM], $from);
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_FROM_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Set FROM clause and remove previous
	 * @param array<string>|array<\StORM\ICollection<T>> $from
	 * @param array<mixed> $values
	 * @return static
	 */
	public function setFrom(array $from, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->removeVars(self::MODIFIER_FROM_FLAG);
		
		$this->removeAlias(self::MODIFIER_FROM);
		$this->addAlias($from, self::MODIFIER_FROM);
		
		$this->modifiers[self::MODIFIER_FROM] = $from;
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_FROM_FLAG);
		}
		
		return $this;
	}
	
	public function getPrefix(bool $dot = true): ?string
	{
		if (!$this->modifiers[self::MODIFIER_FROM]) {
			return null;
		}
		
		$tableName = \reset($this->modifiers[self::MODIFIER_FROM]);
		$alias = \key($this->modifiers[self::MODIFIER_FROM]);
		$dot = $dot ? '.' : '';
		
		if ($alias === 0) {
			return "$tableName$dot";
		}
		
		return $alias ? "$alias$dot" : '';
	}
	
	/**
	 * Set SELECT clause and replace previous
	 * @param array<string> $select
	 * @param array<mixed> $values
	 * @param bool $keepIndex
	 * @return static
	 */
	public function setSelect(array $select, array $values = [], bool $keepIndex = false): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->removeVars(self::MODIFIER_SELECT_FLAG);
		
		$this->modifiers[self::MODIFIER_SELECT] = $select;
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_SELECT_FLAG);
		}
		
		if (!$keepIndex) {
			$this->index = null;
		}
		
		return $this;
	}
	
	/**
	 * Add SELECT clause and merge with previous
	 * @param array<string>|array<\StORM\ICollection<object>> $select
	 * @param array<mixed> $values
	 * @return static
	 */
	public function select(array $select, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_SELECT] = \array_merge($this->modifiers[self::MODIFIER_SELECT], $select);
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_SELECT_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Add LIMIT clause
	 * @param int|null $number
	 * @return static
	 */
	public function setTake(?int $number): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_LIMIT] = $number;
		
		return $this;
	}
	
	/**
	 * @deprecated Use setTake instead
	 * @param int|null $number
	 * @return static
	 */
	public function take(?int $number): self
	{
		return $this->setTake($number);
	}
	
	/**
	 * Add OFFSET clause
	 * @param int|null $number
	 * @return static
	 */
	public function setSkip(?int $number): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_OFFSET] = $number;
		
		return $this;
	}
	
	/**
	 * @deprecated Use setSkip instead
	 * @param int|null $number
	 * @return static
	 */
	public function skip(?int $number): self
	{
		return $this->setSkip($number);
	}
	
	/**
	 * Combine skip() and take() to slice page you want
	 * @param int $page
	 * @param int $onPage
	 * @return static
	 */
	public function setPage(int $page, int $onPage): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->setSkip(($page - 1) * $onPage);
		$this->setTake($onPage);
		
		return $this;
	}
	
	/**
	 * @deprecated Call setPage instead
	 * @param int $page
	 * @param int $onPage
	 * @return static
	 */
	public function page(int $page, int $onPage): self
	{
		return $this->setPage($page, $onPage);
	}
	
	/**
	 * Set ORDER clause and replace previous
	 * @param array<string> $order
	 * @param array<mixed> $values
	 * @return static
	 */
	public function setOrderBy(array $order, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_ORDER_BY] = $order;
		$this->removeVars(self::MODIFIER_ORDER_BY_FLAG);
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_ORDER_BY_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Add ORDER clause and merge with previous
	 * @param array<string> $order
	 * @param array<mixed> $values
	 * @return static
	 */
	public function orderBy(array $order, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_ORDER_BY] = \array_merge($this->modifiers[self::MODIFIER_ORDER_BY], $order);
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_ORDER_BY_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Set GROUP BY and HAVING clause and replace previous
	 * @param array<string> $groups
	 * @param null|string $having
	 * @param array<mixed> $values
	 * @return static
	 */
	public function setGroupBy(array $groups, ?string $having = null, array $values = []): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$this->modifiers[self::MODIFIER_GROUP_BY] = $groups;
		$this->modifiers[self::MODIFIER_HAVING] = $having;
		$this->removeVars(self::MODIFIER_GROUP_BY_FLAG);
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_GROUP_BY_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * @deprecated Use setGroupBy
	 * @param array<string> $groups
	 * @param null|string $having
	 * @param array<mixed> $values
	 * @return static
	 */
	public function groupBy(array $groups, ?string $having = null, array $values = []): self
	{
		$this->setGroupBy($groups, $having, $values);
		
		return $this;
	}
	
	/**
	 * Set GROUP BY for all columns excepts columns in parameter $exceptColumns and HAVING clause and replace previous
	 * @param array<string> $exceptColumns
	 * @param null|string $having
	 * @return static
	 */
	public function setFullGroupBy(array $exceptColumns, ?string $having = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		$groups = $this->modifiers[self::MODIFIER_SELECT];
		
		if (Arrays::contains($groups, '*')) {
			throw new InvalidStateException($this, InvalidStateException::FULL_GROUP_BY_WITH_STAR);
		}
		
		foreach ($exceptColumns as $column) {
			unset($groups[$column]);
		}
		
		$this->modifiers[self::MODIFIER_GROUP_BY] = $groups;
		$this->modifiers[self::MODIFIER_HAVING] = $having;
		
		return $this;
	}
	
	/**
	 * Set JOIN clause and replace previous
	 * @param array<string> $from
	 * @param string|null $condition
	 * @param array<mixed> $values
	 * @param string|null $type
	 * @return static
	 */
	public function setJoin(array $from, ?string $condition = null, array $values = [], ?string $type = null): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		if (!$type) {
			$type = self::DEFAULT_JOIN;
		}
		
		$this->removeAlias(self::MODIFIER_JOIN);
		$this->removeVars(self::MODIFIER_JOIN_FLAG);
		$this->addAlias($from, self::MODIFIER_JOIN);
		
		if ($from) {
			$this->modifiers[self::MODIFIER_JOIN] = [[$type, $from, $condition]];
		}
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_JOIN_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Add JOIN clause and merge with previous
	 * @param array<string>|array<\StORM\ICollection<T>> $from
	 * @param string $condition
	 * @param array<mixed> $values
	 * @param string|null $type
	 * @return static
	 */
	public function join(array $from, string $condition, array $values = [], ?string $type = self::DEFAULT_JOIN): self
	{
		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}
		
		if (!$type) {
			$type = self::DEFAULT_JOIN;
		}
		
		$this->addAlias($from, self::MODIFIER_JOIN);
		
		$this->modifiers[self::MODIFIER_JOIN][] = [$type, $from, $condition];
		
		foreach ($values as $k => $v) {
			$this->bindVar($k, $v, self::MODIFIER_JOIN_FLAG);
		}
		
		return $this;
	}
	
	/**
	 * Specify data which should be serialized to JSON
	 * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize()
	{
		$array = $this->toArray();
		
		foreach ($array as $key => $value) {
			$array[$key] = $value instanceof \JsonSerializable ? $value->jsonSerialize() : (array) $value;
		}
		
		return $array;
	}
	
	/**
	 * Dump modifiers, sql, binders and state of collection
	 * @param bool $return
	 */
	public function dump(bool $return = false): ?string
	{
		$dump = '<br>';
		$dump .= '<strong>' . $this->getSql() . '</strong>';
		$dump .= '<hr>';
		$dump .= '<strong>MODIFIERS:</strong> ';
		$dump .= \json_encode($this->getModifiers(), \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= '<strong>BINDERS:</strong> ';
		$dump .= \json_encode($this->getVars(), \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= '<strong>ALIASES:</strong> ';
		$dump .= \json_encode($this->aliases, \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= 'Connection: ' . $this->getConnection()->getName() . '<br>';
		$dump .= 'Get class: ' . $this->class . '<br>';
		$dump .= 'Is loaded: ' . ($this->isLoaded() ? 'yes' : 'no') . '<br>';
		$dump .= 'Internal index:' . ($this->index ?? 'none') . '<br>';
		$dump .= 'Internal array count:' . ($this->items !== null ? \count($this->items) : 0) . '<br>';
		
		if (!$return) {
			echo $dump;
			
			return null;
		}
		
		return $dump;
	}
	
	/**
	 * Return array of parsed vars, means converted from Entity to primary key, from literal to SQL string
	 * @param int|null $flags
	 * @return array<mixed>
	 */
	public function getVars(?int $flags = null): array
	{
		$return = [];
		
		foreach ($this->vars as $name => $value) {
			if ($flags === null || $this->varsFlags[$name] & $flags) {
				$return[$name] = $value;
			}
		}
		
		return $this->parseVars($return);
	}
	
	/**
	 * Get PDO statement handle. Ff its not created, it will be
	 */
	public function getPDOStatement(): \PDOStatement
	{
		if (!$this->sth) {
			$this->sth = $this->getConnection()->query($this->getSql(), $this->getVars(), [], $this->bufferedQuery);
			$this->sth->setFetchMode(...$this->getFetchParameters());
		}
		
		return $this->sth;
	}
	
	/**
	 * Return the current element
	 * @return T|null
	 */
	public function current(): ?object
	{
		$this->load(false);
		
		return $this->items[\current($this->keys)] ?? null;
	}
	
	/**
	 * Move forward to next element
	 */
	public function next(): void
	{
		$this->load(false);
		
		\next($this->keys);
	}
	
	/**
	 * Return the key of the current element
	 * @return string|int|false
	 */
	public function key()
	{
		$this->load(false);
		
		return \current($this->keys);
	}
	
	/**
	 * Checks if current position is valid
	 */
	public function valid(): bool
	{
		$this->load(false);
		
		return \current($this->keys) !== false;
	}
	
	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind(): void
	{
		$this->keys = \array_keys($this->getItems());
		\reset($this->keys);
	}
	
	/**
	 * Whether a offset exists
	 * @param mixed $offset
	 */
	public function offsetExists($offset): bool
	{
		$this->load(false);
		
		return isset($this->items[$offset]);
	}
	
	/**
	 * Offset to retrieve
	 * @param string|int $offset
	 * @return T
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function offsetGet($offset): object
	{
		$this->load(false);
		
		if (!isset($this->items[$offset])) {
			$source = \is_subclass_of($this->class, Entity::class) ? $this->class : $this->modifiers[self::MODIFIER_FROM];
			
			throw new NotFoundException($this, $this->index ? [$this->index] : [$this->index => $offset], $source);
		}
		
		return $this->items[$offset];
	}
	
	/**
	 * Offset to set
	 * @param string|int $offset
	 * @param T $value
	 */
	public function offsetSet($offset, $value): void
	{
		$this->load(false);
		
		$this->keys[] = $offset;
		$this->items[$offset] = $value;
	}
	
	/**
	 * Offset to unset
	 * @param string|int $offset
	 */
	public function offsetUnset($offset): void
	{
		$this->load(false);
		
		unset($this->keys[\array_search($offset, $this->keys)]);
		unset($this->items[$offset]);
	}
	
	/**
	 * Fetch and close cursor, if property is not null fetch the property
	 * @param bool $needed
	 * @param callable|null $callback
	 * @phpstan-return T|null
	 * @throws \StORM\Exception\NotFoundException
	 */
	protected function fetchCloned(bool $needed, ?callable $callback = null): ?object
	{
		$collection = clone $this;
		
		if ($callback) {
			\call_user_func($callback, $collection);
		}
		
		$sth = $collection->getPDOStatement();
		
		$object = $sth->fetch();
		
		$sth->closeCursor();
		
		if ($object === false && $needed) {
			throw new NotFoundException($this, $this->modifiers[self::MODIFIER_WHERE], \is_subclass_of($this->class, Entity::class) ? $this->class : $this->modifiers[self::MODIFIER_FROM]);
		}
		
		return \Nette\Utils\Helpers::falseToNull($object);
	}
	
	/**
	 * @param array<mixed> $vars
	 * @return array<mixed>
	 */
	protected function parseVars(array $vars): array
	{
		foreach ($vars as $name => $value) {
			if (\is_scalar($value) || \is_null($value)) {
				$vars[$name] = \is_bool($value) ? (int) $value : $value;
				
				continue;
			}
			
			if ($value instanceof Entity) {
				$vars[$name] = (string) $value;
				
				continue;
			}
			
			$type = \is_object($value) ? \get_class($value) : \gettype($value);
			
			throw new InvalidStateException($this, InvalidStateException::INVALID_BINDER_VAR, "$name of $type");
		}
		
		return $vars;
	}
	
	/**
	 * @param string $sql
	 * @param array<mixed> $vars
	 */
	protected function replaceLiterals(string $sql, array $vars): string
	{
		foreach ($vars as $name => $value) {
			if ($value instanceof Literal) {
				$sql = Strings::replace($sql, "/\:$name/", (string) $value);
			}
		}
		
		return $sql;
	}
	
	/**
	 * @return array<mixed>
	 */
	protected function getFetchParameters(): array
	{
		$mode = \PDO::FETCH_CLASS;
		
		if ($this->index !== null) {
			$mode |= \PDO::FETCH_UNIQUE;
		}
		
		return [$mode, $this->class, $this->classArguments];
	}
	
	protected function init(): void
	{
		$this->setSelect($this->baseSelect, [], true);
		
		$this->modifiers[self::MODIFIER_FROM] = [];
		
		if ($this->baseFrom !== null) {
			$this->setFrom($this->baseFrom);
		}
		
		$this->modifiers[self::MODIFIER_JOIN] = [];
		$this->modifiers[self::MODIFIER_WHERE] = [];
		$this->modifiers[self::MODIFIER_GROUP_BY] = [];
		$this->modifiers[self::MODIFIER_HAVING] = null;
		$this->modifiers[self::MODIFIER_ORDER_BY] = [];
		$this->modifiers[self::MODIFIER_LIMIT] = null;
		$this->modifiers[self::MODIFIER_OFFSET] = null;
		
		$this->binderCounter = 0;
		$this->vars = [];
		$this->varsFlags = [];
		$this->affectedNumber = null;
		$this->possibleValues = [];
	}
	
	/**
	 * @param string $name
	 * @param mixed $value
	 * @param int $modifierFlag
	 */
	protected function bindVar(string $name, $value, int $modifierFlag): void
	{
		if (isset($this->vars[$name])) {
			throw new AlreadyExistsException($this, AlreadyExistsException::BIND_VAR, $name);
		}
		
		$this->vars[$name] = $value;
		
		if (isset($this->varsFlags[$name])) {
			$this->varsFlags[$name] |= $modifierFlag;
		} else {
			$this->varsFlags[$name] = $modifierFlag;
		}
	}
	
	protected function removeVars(int $flagToRemove): void
	{
		foreach ($this->varsFlags as $var => $flag) {
			$this->varsFlags[$var] = $flag & ~$flagToRemove;
			
			if ($this->varsFlags[$var] !== 0) {
				continue;
			}
			
			unset($this->varsFlags[$var]);
			unset($this->vars[$var]);
		}
	}
	
	/**
	 * @return array<T>
	 * @throws \StORM\Exception\GeneralException
	 */
	protected function getItems(): array
	{
		if ($this->items !== null) {
			return $this->items;
		}
		
		$this->load();

		/** @phpstan-ignore-next-line */
		return $this->items;
	}

	private function generateBinder(): string
	{
		$binder = $this->binderName . $this->binderCounter;
		$this->binderCounter++;
		
		return $binder;
	}
	
	/**
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @param bool $not
	 * @param bool $replace
	 */
	private function processWhere(string $expression, $values, bool $not, bool $replace): void
	{
		$this->possibleValues = [];
		
		if ($replace) {
			$this->removeVars(self::MODIFIER_WHERE_FLAG);
			$this->modifiers[self::MODIFIER_WHERE] = [];
		}
		
		if ($values === null) {
			$this->modifiers[self::MODIFIER_WHERE][] = $not ? "!($expression)" : "($expression)";
			
			return;
		}
		
		if (!\is_array($values)) {
			$values = [$values];
		}
		
		$isAssociative = Helpers::isAssociative($values);
		$count = \count($values);
		
		if ($isAssociative && $count) {
			if (!Strings::contains($expression, ':')) {
				throw new \InvalidArgumentException("Passed associative array and there is no bind variable with ':'. Call \array_values() or toArrayOf(..., true).");
			}
			
			$this->modifiers[self::MODIFIER_WHERE][] = $not ? "!($expression)" : "($expression)";
			
			foreach ($values as $k => $v) {
				$this->bindVar((string) $k, $v, self::MODIFIER_WHERE_FLAG);
			}
		} else {
			if ($count === 1 && \array_key_exists(0, $values)) {
				$binder = $this->generateBinder();
				
				if ($values[0] === null) {
					$this->modifiers[self::MODIFIER_WHERE][] = $not ? "($expression IS NOT NULL)" : "($expression IS NULL)";
				} else {
					$this->modifiers[self::MODIFIER_WHERE][] = $not ? "($expression != :$binder)" : "($expression = :$binder)";
					$this->bindVar($binder, $values[0], self::MODIFIER_WHERE_FLAG);
					
					if (!$not) {
						$this->possibleValues[$expression] = $values;
					}
				}
			} elseif ($count !== 0) {
				$nullValue = false;
				$i = 0;
				$inFragment = '';
				
				foreach ($values as $v) {
					if ($v === null) {
						$nullValue = true;
						
						continue;
					}
					
					$binder = $this->generateBinder();
					
					if ($i !== 0) {
						$inFragment .= ',';
					}
					
					$aux = ":$binder";
					$this->bindVar($binder, $v, self::MODIFIER_WHERE_FLAG);
					
					$inFragment .= $aux;
					$i++;
				}
				
				$where = $not ? "$expression NOT IN ($inFragment)" : "$expression IN ($inFragment)";
				
				if ($nullValue) {
					$where .= $not ? " AND $expression IS NOT NULL" : " OR $expression IS NULL";
				}
				
				if (!$not) {
					$this->possibleValues[$expression] = $values;
				}
				
				$this->modifiers[self::MODIFIER_WHERE][] = "($where)";
			} else {
				if (!$not) {
					$this->modifiers[self::MODIFIER_WHERE][] = '(1 = 0)';
				}
			}
		}
	}
	
	/**
	 * @return null|string|int|bool
	 * @throws \StORM\Exception\NotFoundException
	 */
	private function getValue(?string $property, bool $needed, ?callable $callback = null)
	{
		$collection = clone $this;
		
		if ($property !== null) {
			isset($this->modifiers[self::MODIFIER_SELECT][$property]) ? $collection->setSelect([$property => $this->modifiers[self::MODIFIER_SELECT][$property]]) : $collection->setSelect([$property]);
		}
		
		if ($callback) {
			\call_user_func($callback, $collection);
		}
		
		$sth = $collection->getPDOStatement();
		$result = $sth->fetchColumn();
		$sth->closeCursor();
		
		if ($result === false && $needed) {
			throw new NotFoundException($this, $this->modifiers[self::MODIFIER_WHERE], \is_subclass_of($this->class, Entity::class) ? $this->class : $this->modifiers[self::MODIFIER_FROM]);
		}
		
		return $result;
	}
	
	/**
	 * @param bool $select
	 * @param bool $from
	 * @param array<string> $indexSelect
	 */
	private function createSqlPrefix(bool $select, bool $from, array $indexSelect = []): string
	{
		$sql = '';
		
		if ($select) {
			$sql .= Helpers::createSqlClauseString(self::MODIFIER_SELECT, \array_merge($indexSelect, $this->modifiers[self::MODIFIER_SELECT]), ',', ' AS ');
		}
		
		if ($from) {
			$sql .= Helpers::createSqlClauseString(($select ? ' ' : '') . self::MODIFIER_FROM, $this->modifiers[self::MODIFIER_FROM], ',', ' AS ');
		}
		
		foreach ($this->modifiers[self::MODIFIER_JOIN] as $join) {
			[$type, $aliases, $condition] = $join;
			$sql .= Helpers::createSqlClauseString(' ' . $type . ' ' . self::MODIFIER_JOIN, $aliases, ',', ' AS ') . ' ON (' . $condition . ')';
		}
		
		return $sql;
	}
	
	private function createSqlSuffix(bool $having, bool $orderBy): string
	{
		$sql = Helpers::createSqlClauseString(' ' . self::MODIFIER_WHERE, $this->modifiers[self::MODIFIER_WHERE], ' AND ');
	
		if ($having) {
			$sql .= Helpers::createSqlClauseString(' ' . self::MODIFIER_GROUP_BY, $this->modifiers[self::MODIFIER_GROUP_BY], ',');
			$sql .= $this->modifiers[self::MODIFIER_HAVING] === null ? '' : ' ' . self::MODIFIER_HAVING . ' ' . $this->modifiers[self::MODIFIER_HAVING];
		}
		
		if ($orderBy) {
			$sql .= Helpers::createSqlClauseString(' ' . self::MODIFIER_ORDER_BY, $this->modifiers[self::MODIFIER_ORDER_BY], ',', ' ', false, true);
		}
		
		$sql .= $this->modifiers[self::MODIFIER_LIMIT] === null ? '' : ' ' . self::MODIFIER_LIMIT . ' ' . $this->modifiers[self::MODIFIER_LIMIT];
		
		if ($this->modifiers[self::MODIFIER_LIMIT] === null && $this->modifiers[self::MODIFIER_OFFSET] !== null) {
			$sql .= ' ' . self::MODIFIER_LIMIT . ' ' . self::MAX_LIMIT;
		}
		
		$sql .= $this->modifiers[self::MODIFIER_OFFSET] === null ? '' : ' ' . self::MODIFIER_OFFSET . ' ' . $this->modifiers[self::MODIFIER_OFFSET];
		
		return $sql;
	}
	
	/**
	 * @param array<string>|array<\StORM\ICollection<T>> $aliases
	 * @param string $modifier
	 */
	private function addAlias(array $aliases, string $modifier): void
	{
		foreach ($aliases as $alias => $table) {
			if (\is_int($alias)) {
				$alias = (string) $table;
			}
			
			if (Strings::substring($alias, 0, 1) === $this->getConnection()->getQuoteIdentifierChar()) {
				$alias = Strings::substring($alias, 1, -1);
			}
			
			if (!Helpers::isValidIdentifier($alias)) {
				throw new InvalidStateException($this, InvalidStateException::INVALID_IDENTIFIER, $alias);
			}
			
			if (isset($this->aliases[$alias])) {
				throw new AlreadyExistsException($this, AlreadyExistsException::ALIAS, $alias);
			}
			
			$this->aliases[$alias] = $modifier;
			$this->tableAliases[\is_string($table) ? $table : $alias] = $alias;
		}
	}
	
	private function removeAlias(string $remove): void
	{
		foreach ($this->aliases as $alias => $modifier) {
			if ($modifier === $remove) {
				unset($this->aliases[$alias]);
			}
		}
	}
	
	/**
	 * Convert collection to array of sprintf formated strings
	 * Return sprintf formated array
	 * @param string $format
	 * @param array<string>|array<callable> $callbacks or $columns
	 * @return array<string>
	 */
	private function format(string $format, array $callbacks = []): array
	{
		$return = [];
		$i = 1;
		
		foreach ($this->getItems() as $index => $value) {
			$args = [];
			
			foreach ($callbacks as $cb) {
				if (\is_callable($cb)) {
					$args[] = \call_user_func_array($cb, [$value]);
				} elseif ($cb === self::ITERATOR_WILDCARD) {
					$args[] = $i;
				} else {
					$args[] = $value->$cb;
				}
			}
			
			$i++;
			$return[$index] = \vsprintf($format, $args);
		}
		
		return $return;
	}
	
	/**
	 * @return array<string>
	 */
	public function __sleep(): array
	{
		$this->clear();
		
		$vars = \get_object_vars($this);
		unset($vars['connection'], $vars['sth']);
		
		return \array_keys($vars);
	}
	
	public function __wakeup(): void
	{
		$this->connection = null;
		$this->sth = null;
	}
	
	/**
	 * Get real SQL string
	 */
	public function __toString(): string
	{
		return '(' . $this->getSql() . ')';
	}
}
