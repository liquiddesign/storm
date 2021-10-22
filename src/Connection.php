<?php

declare(strict_types=1);

namespace StORM;

use StORM\Exception\GeneralException;

class Connection
{
	/**
	 * Mysql quote char
	 */
	private const QUOTE_CHAR_MYSQL = '`';
	
	/**
	 * Mssql quote char
	 */
	private const QUOTE_CHAR_OTHER = '"';
	
	private \PDO $link;

	private string $name;
	
	/**
	 * @var int[]
	 */
	private array $attributes;
	
	private string $user;
	
	private string $driver;
	
	private bool $debug = false;
	
	/**
	 * Restrict char
	 **/
	private string $quoteChar;
	
	/**
	 * @var \StORM\LogItem[]
	 */
	private array $log = [];
	
	/**
	 * @var callable|null
	 */
	private $primaryKeyGenerator;
	
	/**
	 * Connection constructor.
	 * @param string $name
	 * @param string $dsn
	 * @param string $user
	 * @param string $password
	 * @param int[] $attributes
	 */
	public function __construct(string $name, string $dsn, string $user, string $password, array $attributes = [])
	{
		$this->name = $name;
		$parsedDsn = \explode(':', $dsn, 2);
		$this->driver = $parsedDsn[0];
		\parse_str(\str_replace(';', '&', $parsedDsn[1]), $matches);
		$this->user = $user;
		$this->quoteChar = $this->driver === 'mysql' ? self::QUOTE_CHAR_MYSQL : self::QUOTE_CHAR_OTHER;
		$this->attributes = $attributes;
		$this->link = new \PDO($dsn, $user, $password, $attributes);
	}
	
	/**
	 * Get internal name of connection
	 */
	public function getName(): string
	{
		return $this->name;
	}
	
	/**
	 * Return PDO object
	 */
	public function getLink(): \PDO
	{
		return $this->link;
	}
	
	/**
	 * Get user of current connection
	 */
	public function getUser(): ?string
	{
		return $this->user;
	}
	
	/**
	 * Get database name of current connection
	 */
	public function getDatabaseName(): ?string
	{
		return $this->query('SELECT DATABASE()', [], [], null, false)->fetchColumn(0);
	}
	
	/**
	 * Get PDO configuration attributes
	 * @return int[]
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}
	
	/**
	 * Get quote chararcter for quoting identifiers
	 */
	public function getQuoteIdentifierChar(): string
	{
		return $this->quoteChar;
	}
	
	/**
	 * Quote identifier
	 * @param string $string restrict
	 */
	public function quoteIdentifier(string $string): string
	{
		return $this->quoteChar . $string . $this->quoteChar;
	}
	
	/**
	 * Quote volue, wraps PDO quote
	 * @param string $string restrict
	 */
	public function quote(string $string): string
	{
		return $this->link->quote($string);
	}
	
	/**
	 * Execute query with binding vars and quoting identifiers and return PDO statement
	 * Identifiers are parsed by sprintf
	 * @param string $sql
	 * @param string[] $vars
	 * @param string[] $identifiers
	 * @param bool|null $bufferedQuery
	 * @param bool|null $debug
	 */
	public function query(string $sql, array $vars = [], array $identifiers = [], ?bool $bufferedQuery = null, ?bool $debug = null): \PDOStatement
	{
		if ($debug === null) {
			$debug = $this->debug;
		}
		
		if (\count($identifiers) > 0) {
			$quoted = [];
			
			foreach ($identifiers as $id) {
				$quoted[] = $this->quoteIdentifier($id);
			}
			
			$sql = \vsprintf($sql, $quoted);
		}
		
		if ($debug) {
			$ts = \microtime(true);
			$item = $this->log($sql, $vars);
			$item->setError(true);
		}
		
		if (\count($vars) > 0) {
			$sth = $this->getLink()->prepare($sql);
			
			if ($bufferedQuery !== null) {
				$tmpValue = $this->getLink()->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
				$this->getLink()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $bufferedQuery);
			}
			
			$sth->execute($vars);
			
			if (isset($tmpValue)) {
				$this->getLink()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $tmpValue);
			}
		} else {
			$sth = $this->getLink()->query($sql);
		}
		
		if (isset($item) && isset($ts)) {
			$item->addTime(\microtime(true) - $ts);
			$item->setError(false);
		}
		
		return $sth;
	}
	
	/**
	 * Get collection of rows
	 * @param string[]|null $from
	 * @param string[] $select
	 * @param string $class
	 * @param mixed[] $classParameters
	 * @param string|null $index
	 */
	public function rows(?array $from = null, array $select = ['*'], string $class = \stdClass::class, array $classParameters = [], ?string $index = null): GenericCollection
	{
		return new GenericCollection($this, $from, $select, $class, $classParameters, $index);
	}
	
	/**
	 * Create row = insert row into table
	 * @param string $table
	 * @param mixed[]|object $values
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 */
	public function createRow(string $table, $values, bool $ignore = false, ?string $nonAutoincrementPK = null): InsertResult
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		$vars = [];
		$primaryKeys = [];
		
		$values = $this->prepareInputArray($values, $primaryKeys, $nonAutoincrementPK);
		
		$sql = $this->getSqlInsert($table, [$values], $vars, [], $ignore);
		
		$beforeId = (int) $this->getLink()->lastInsertId();
		$sth = $this->query($sql, $vars);
		$rowCount = $sth->rowCount();
		$sth->closeCursor();
		
		return new InsertResult($this, $table, false, $ignore, $rowCount, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Create multiple rows at once
	 * @param string $table
	 * @param mixed[][]|object[] $manyValues
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @param int $chunkSize
	 */
	public function createRows(string $table, array $manyValues, bool $ignore = false, ?string $nonAutoincrementPK = null, int $chunkSize = 100): InsertResult
	{
		$affected = 0;
		$primaryKeys = [];
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		/** @var mixed[]|object $values */
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			$values = $this->prepareInputArray($values, $primaryKeys, $nonAutoincrementPK);
			
			$vars = [];
			
			$sql = $this->getSqlInsert($table, $values, $vars, [], $ignore);
			
			$sth = $this->query($sql, $vars);
			$affected += $sth->rowCount();
			$sth->closeCursor();
		}
		
		return new InsertResult($this, $table, true, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Synchronize row by unique index, if $columnsToUpdate is null all columns are updated
	 * @param string $table
	 * @param mixed[]|object $values
	 * @param string[]|\StORM\Literal[]|null $columnsToUpdate
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 */
	public function syncRow(string $table, $values, ?array $columnsToUpdate = null, bool $ignore = false, ?string $nonAutoincrementPK = null): InsertResult
	{
		$primaryKeys = [];
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		$values = $this->prepareInputArray($values, $primaryKeys, $nonAutoincrementPK);
		$vars = [];
		$sql = $this->getSqlInsert($table, [$values], $vars, $columnsToUpdate, $ignore);
		
		$sth = $this->query($sql, $vars);
		$affected = $sth->rowCount();
		$sth->closeCursor();
		
		return new InsertResult($this, $table, false, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Synchronize rows by unique index, if $columnsToUpdate is null all columns are updated
	 * @param string $table
	 * @param mixed[][]|object[] $manyValues
	 * @param string[]|\StORM\Literal[]|null $columnsToUpdate
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @param int $chunkSize
	 */
	public function syncRows(string $table, array $manyValues, ?array $columnsToUpdate = null, bool $ignore = false, ?string $nonAutoincrementPK = null, int $chunkSize = 100): InsertResult
	{
		$affected = 0;
		$primaryKeys = [];
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		/** @var mixed[]|object $values */
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			$values = $this->prepareInputArray($values, $primaryKeys, $nonAutoincrementPK);
			$vars = [];
			$sql = $this->getSqlInsert($table, $values, $vars, $columnsToUpdate, $ignore);
			$sth = $this->query($sql, $vars);
			$affected += $sth->rowCount();
			$sth->closeCursor();
		}
		
		return new InsertResult($this, $table, true, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Generate SQL string for insert
	 * @param string $table
	 * @param mixed[][] $manyInserts
	 * @param mixed[] $vars
	 * @param string[]|\StORM\Literal[]|null $onDuplicateUpdate
	 * @param bool $ignore
	 */
	public function getSqlInsert(string $table, array $manyInserts, array &$vars, ?array $onDuplicateUpdate, bool $ignore = false): string
	{
		$sql = '';
		$i = 0;
		$noInserts = null;
		$firstCycle = true;
		$values = $binds = [];
		
		foreach ($manyInserts as $inserts) {
			if ($i === 0) {
				$noInserts = \count($inserts);
			}
			
			if ($noInserts === 0) {
				throw new \InvalidArgumentException("There is no columns to insert");
			}
			
			if ($noInserts !== \count($inserts)) {
				throw new \InvalidArgumentException("Columns count mismatch");
			}
			
			$binds = [];
			
			foreach ($inserts as $property => $rawValue) {
				$this->bindVariables($property, $rawValue, $values, $binds, '', (string) $i);
			}
			
			if ($firstCycle) {
				$flags = $ignore ? ' IGNORE' : '';
				$sql .= Helpers::createSqlClauseString("INSERT$flags INTO $table", \array_values($binds), ',', '', true);
				$sql .= ' VALUES';
			} else {
				$sql .= ',';
			}
			
			$sql .= Helpers::createSqlClauseString('', \array_keys($binds), ',', '', true);
			$i++;
			$firstCycle = false;
		}
		
		if ($onDuplicateUpdate === null || $onDuplicateUpdate) {
			$i = 0;
			$sql .= ' ON DUPLICATE KEY UPDATE ';
			
			$onDuplicateUpdate = $onDuplicateUpdate ?: \array_values($binds);
			
			foreach ($onDuplicateUpdate as $name => $value) {
				if ($i !== 0) {
					$sql .= ',';
				}
				
				if (\is_int($name)) {
					$name = $value;
				}
				
				$sql .= $name. '=' . ($value instanceof Literal ? (string) $value : 'VALUES('. $name . ')');
				$i++;
			}
		}
		
		$vars = $values;
	
		return $sql;
	}
	
	/**
	 * Get PDO driver
	 */
	public function getDriver(): string
	{
		return $this->driver;
	}
	
	/**
	 * Execute query and return affected rows
	 * @param string $sql
	 * @param string[] $identifiers
	 * @param bool|null $debug
	 */
	public function exec(string $sql, array $identifiers = [], ?bool $debug = null): int
	{
		if ($debug === null) {
			$debug = $this->debug;
		}
		
		if ($debug) {
			$ts = \microtime(true);
			$item = $this->log($sql, []);
			$item->setError(true);
		}
		
		if (\count($identifiers) > 0) {
			$quoted = [];
			
			foreach ($identifiers as $id) {
				$quoted[] = $this->quoteIdentifier($id);
			}
			
			$sql = \vsprintf($sql, $quoted);
		}
		
		$return = $this->getLink()->exec($sql);
		
		if (isset($item) && isset($ts)) {
			$item->addTime(\microtime(true) - $ts);
			$item->setError(false);
		}
		
		return $return;
	}
	
	/**
	 * Get all logged items if debug mode is on
	 * @return \StORM\LogItem[]
	 */
	public function getLog(): array
	{
		return $this->log;
	}
	
	/**
	 * Get real sql. Variables are binded.
	 * @param string $sql
	 * @param mixed[] $vars
	 */
	public function getRealSql(string $sql, array $vars): string
	{
		return \PdoDebugger::show($sql, $vars);
	}
	
	/**
	 * Get last logged item
	 */
	public function getLastLogItem(): ?LogItem
	{
		return \end($this->log);
	}
	
	/**
	 * Tells if debug mode is on
	 */
	public function isDebug(): bool
	{
		return $this->debug;
	}
	
	/**
	 * Set primary key generator
	 * If callback is null, primary key will be handled as autoincrement
	 * @param callable|null $callback
	 */
	public function setPrimaryKeyGenerator(?callable $callback): void
	{
		$this->primaryKeyGenerator = $callback;
		
		return;
	}
	
	/**
	 * Generate primary key
	 */
	public function generatePrimaryKey(): ?string
	{
		$generator = $this->primaryKeyGenerator;
		
		return $generator ? (string) $generator() : null;
	}
	
	/**
	 * Turn on or turn off debug mode
	 * @param bool $debug
	 */
	public function setDebug(bool $debug): void
	{
		$this->debug = $debug;
	}
	
	public function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, string $prefix = ''): void
	{
		Helpers::bindVariables($property, $rawValue, $values, $binds, $varPrefix, $varPostfix, [], $prefix);
		
		return;
	}
	
	public static function generateUuid(?string $namespace = null, ?string $string = null): string
	{
		if ($namespace !== null && $string !== null) {
			return \md5($namespace . '!._.!' . $string);
		}
		
		return \str_replace('.', '', \uniqid('', true) . \rand(10, 99));
	}
	
	/**
	 * @param mixed[]|object $values
	 * @param string[] $primaryKeys
	 * @param string|null $nonAutoincrementPK
	 * @return mixed[]
	 */
	private function prepareInputArray($values, array &$primaryKeys, ?string $nonAutoincrementPK): array
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		if ($nonAutoincrementPK && !isset($values[$nonAutoincrementPK])) {
			if ($generatedPrimaryKey = $this->generatePrimaryKey()) {
				$values[$nonAutoincrementPK] = $generatedPrimaryKey;
			}
		}
		
		if ($nonAutoincrementPK && isset($values[$nonAutoincrementPK])) {
			$primaryKeys[] = $values[$nonAutoincrementPK];
		}
		
		return $values;
	}
	
	/**
	 * @param string $sql
	 * @param mixed[] $vars
	 */
	private function log(string $sql, array $vars): LogItem
	{
		$item = new LogItem($sql, $vars);
		
		if (isset($this->log[$sql])) {
			$item->setAmount($this->log[$sql]->getAmount() + 1);
			$item->setVars($vars);
			$item->addTime($this->log[$sql]->getTotalTime());
			unset($this->log[$sql]);
		}
		
		$this->log[$sql] = $item;
		
		return $item;
	}
	
	/**
	 * Sleep
	 * @throws \StORM\Exception\GeneralException
	 * @return string[]
	 */
	public function __sleep(): array
	{
		throw new GeneralException('StORM connections are unserializable');
	}
}
