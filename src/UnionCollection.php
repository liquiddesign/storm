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
 * Class Union Collection
 * @template T of \StORM\Entity
 * @implements \ArrayAccess<string|int, T>
 * @implements \Iterator<string|int, T>
 * @implements \StORM\ISearchableCollection<T>
 */
class UnionCollection implements ISearchableCollection, IDumper, \Iterator, \ArrayAccess, \JsonSerializable, \Countable
{
	protected const ITERATOR_WILDCARD = '__iterator';

	protected const MODIFIER_LIMIT = 'LIMIT';

	protected const MODIFIER_OFFSET = 'OFFSET';

	protected const MODIFIER_ORDER_BY = 'ORDER BY';

	protected const MODIFIER_ORDER_BY_FLAG = 16;

	protected const ORDER_ASC = 'ASC';

	protected const ORDER_DESC = 'DESC';

	/**
	 * According to MySQL
	 */
	protected const MAX_LIMIT = '18446744073709551615';

	/**
	 * @var array<T>|null
	 */
	protected ?array $items = null;

	/**
	 * @var array<string|int>
	 */
	protected array $keys;

	protected ?string $index;

	/**
	 * @var array<mixed>
	 */
	protected array $vars = [];

	/**
	 * @var array<int>
	 */
	protected array $varsFlags = [];

	/**
	 * @var array<mixed>
	 */
	protected array $modifiers = [];

	/**
	 * @var array<mixed>
	 */
	protected array $classArguments = [];

	protected ?bool $bufferedQuery = null;

	protected ?\PDOStatement $sth = null;

	private Connection $connection;

	/**
	 * @var array<\StORM\ICollection<T>>
	 */
	private array $collections;

	private string $class;

	/**
	 * @param array<\StORM\ICollection<T>> $collections
	 */
	public function __construct(array $collections, Connection $connection, string $index, string $class = \stdClass::class)
	{

		$this->collections = $collections;
		$this->connection = $connection;
		$this->class = $class;
		$this->index = $index;
	}

	public function getConnection(): Connection
	{
		return $this->connection;
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
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
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
			throw new NotFoundException($this, $this->index ? [$this->index] : [$this->index => $offset], null);
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
	 * Count internal array if loaded, otherwise call enum()
	 */
	public function count(): int
	{
		return \count($this->getItems());
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

		$sth = $this->getPDOStatement();
		$this->items = \Nette\Utils\Helpers::falseToNull($sth->fetchAll(...$this->getFetchParameters()));
		$sth->closeCursor();

		if ($this->items === null) {
			throw new GeneralException('Load collection failed:' . \implode(':', $this->getConnection()->getLink()->errorInfo()));
		}

		$this->keys = \array_keys($this->items);

		return $this;
	}

	public function isLoaded(): bool
	{
		return $this->items !== null;
	}

	/**
	 * Take 1, fetch the column and close cursor
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
	 * Take 1, fetch the last column and close cursor
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
	 * @param string|null $class
	 * @param array<mixed>|null $classArguments
	 * @return T|null
	 */
	public function fetch(?string $class = null, ?array $classArguments = null): ?object
	{
		if ($class === \stdClass::class) {
			$classArguments = [];
		}

		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}

		if (!$this->sth) {
			$sth = $this->getConnection()->query($this->getSql(), $this->getVars(), [], $this->bufferedQuery);
			$sth->setFetchMode(...$this->getFetchParameters(\PDO::FETCH_CLASS, $class, $classArguments));

			$this->sth = $sth;
		}

		return \Nette\Utils\Helpers::falseToNull($this->sth->fetch());
	}

	public function getPDOStatement(): \PDOStatement
	{
		$sth = $this->getConnection()->query($this->getSql(), $this->getVars(), [], $this->bufferedQuery);
		$sth->setFetchMode(...$this->getFetchParameters());

		return $sth;
	}

	public function getSql(): string
	{
		$collectionSql = [];

		foreach ($this->collections as $collection) {
			$collectionSql[] = '(' . $collection->getSql() . ')';
		}

		$sql = \implode(' UNION ', $collectionSql);

		$sql .= $this->createSqlSuffix(true);

		return $this->replaceLiterals($sql, $this->vars);
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

		foreach ($this->collections as $collection) {
			$return += $collection->getVars();
		}

		return $this->parseVars($return);
	}

	/**
	 * Convert collection to array of object
	 * @param bool $toArrayValues
	 * @phpstan-return array<T>|array<\stdClass>
	 * @return array<object>|array<\stdClass>
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
	 * Fetch as array of class types $class
	 * @template X
	 * @param class-string<X> $class
	 * @param array<mixed> $classArgs
	 * @param bool $toArrayValues
	 * @return array<X>
	 */
	public function fetchArray(string $class, array $classArgs = [], bool $toArrayValues = false): array
	{
		$return = $this->fetchAllCloned(\PDO::FETCH_CLASS, $class, $classArgs);

		return $toArrayValues ? \array_values($return) : $return;
	}

	/**
	 * Fetch columns into array
	 * @return array<string>
	 */
	public function fetchColumns(string $column, bool $toArrayValues = false): array
	{
		$collections = $this->collections;

		$return = $this->fetchAllCloned(\PDO::FETCH_COLUMN, null, null, function (ICollection $collection) use ($column, $collections): void {
			unset($collection);

			foreach ($collections as $collection) {
				$collection->setSelect([$column], [], true);
			}
		});

		return $toArrayValues ? \array_values($return) : $return;
	}

	public function setIndex(?string $index, bool $prefixIndex = true): ISearchableCollection
	{
		unset($prefixIndex);

		if ($this->isLoaded()) {
			throw new InvalidStateException($this, InvalidStateException::COLLECTION_ALREADY_LOADED);
		}

		$this->index = $index;

		return $this;
	}

	/**
	 * Set WHERE condition and replace previous
	 * @param string|null $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhere(?string $expression, $values = null): self
	{
		foreach ($this->collections as $collection) {
			$collection->setWhere($expression, $values);
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
		foreach ($this->collections as $collection) {
			$collection->where($expression, $values);
		}

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
		foreach ($this->collections as $collection) {
			$collection->whereMatch($conditions, $columnPrefix);
		}

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
		foreach ($this->collections as $collection) {
			$collection->whereBetween($expression, $from, $to, $fromEquals, $toEquals);
		}

		return $this;
	}

	/**
	 * Set WHERE negated condition and replace previous
	 * @param string $expression
	 * @param array<mixed>|null|mixed $values
	 * @return static
	 */
	public function setWhereNot(string $expression, $values = null): self
	{
		foreach ($this->collections as $collection) {
			$collection->setWhereNot($expression, $values);
		}

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
		foreach ($this->collections as $collection) {
			$collection->whereNot($expression, $values);
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
	 * Specify data which should be serialized to JSON
	 * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	#[\ReturnTypeWillChange]
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
		$dump .= '<hr>';
		$dump .= 'Connection: ' . $this->getConnection()->getName() . '<br>';
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
	 * Get array of modifiers: WHERE, FROM, SELECT, LIMIT, OFFSET, ORDER BY, GROUP BY, HAVING BY, JOIN
	 * @return array<mixed>
	 */
	public function getModifiers(): array
	{
		return $this->modifiers;
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
	 * @param int $mode
	 * @param string|null $class
	 * @param array<mixed>|null $classArguments
	 * @return array<mixed>
	 */
	protected function getFetchParameters(int $mode = \PDO::FETCH_CLASS, ?string $class = null, ?array $classArguments = null): array
	{
		if ($this->index !== null && !($mode & \PDO::FETCH_GROUP)) {
			$mode |= \PDO::FETCH_UNIQUE;
		}

		return $mode & \PDO::FETCH_CLASS ? [$mode, $class ?: $this->class, $classArguments ?? $this->classArguments] : [$mode];
	}

	/**
	 * @param int $fetchMode
	 * @param string|null $fetchClass
	 * @param array<mixed>|null $fetchArguments
	 * @param callable|null $callback
	 * @return array<mixed>
	 * @throws \StORM\Exception\GeneralException
	 */
	protected function fetchAllCloned(int $fetchMode, ?string $fetchClass = null, ?array $fetchArguments = null, ?callable $callback = null): array
	{
		$collection = clone $this;

		if ($callback) {
			\call_user_func($callback, $collection);
		}

		$sth = $this->getConnection()->query($collection->getSql(), $collection->getVars(), [], $this->bufferedQuery);

		$result = \Nette\Utils\Helpers::falseToNull($sth->fetchAll(...$this->getFetchParameters($fetchMode, $fetchClass, $fetchArguments)));

		if ($result === null) {
			throw new GeneralException('Fetch collection failed:' . \implode(':', $this->getConnection()->getLink()->errorInfo()));
		}

		return $result;
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
			throw new NotFoundException($this, [], \is_subclass_of($this->class, Entity::class) ? $this->class : []);
		}

		return \Nette\Utils\Helpers::falseToNull($object);
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
	 * @return null|string|int|bool
	 * @throws \StORM\Exception\NotFoundException
	 */
	private function getValue(?string $property, bool $needed, ?callable $callback = null)
	{
		$collection = clone $this;

		if ($property !== null) {
			foreach ($this->collections as $subCollection) {
				$subCollection->setSelect([$property]);
			}
		}

		if ($callback) {
			\call_user_func($callback, $collection);
		}

		$sth = $collection->getPDOStatement();
		$result = $sth->fetchColumn();
		$sth->closeCursor();

		if ($result === false && $needed) {
			throw new NotFoundException($this, [], \is_subclass_of($this->class, Entity::class) ? $this->class : []);
		}

		return $result;
	}

	private function createSqlSuffix(bool $orderBy): string
	{
		$sql = '';

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
	 * Get real SQL string
	 */
	public function __toString(): string
	{
		return '(' . $this->getSql() . ')';
	}
}
