<?php

declare(strict_types=1);

namespace StORM;

use Nette\DI\Container;
use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Meta\Structure;

class Connection
{
	/**
	 * @var \Nette\DI\Container
	 */
	private $container;
	
	/**
	 * @var \PDO
	 */
	private $link;

	/**
	* @var string
	*/
	private $name;
	
	/**
	 * @var int[]
	 */
	private $attributes;
	
	/**
	 * @var string
	 */
	private $user;
	
	/**
	 * @var string
	 */
	private $driver;
	
	/**
	 * @var bool
	 */
	private $debug;
	
	/**
	 * Restrict char
	 * @var string
	 **/
	private $quoteChar;
	
	/**
	 * @var string
	 */
	private $mutation;
	
	/**
	 * @var string[]
	 */
	private $availableMutations = [];
	
	/**
	 * @var callable|null
	 */
	private $primaryKeyGenerator;
	
	/**
	 * @var string[]
	 */
	private $customAnnotations = [];
	
	/**
	 * @var \StORM\LogItem[]
	 */
	private $log = [];
	
	/**
	 * Mysql quote char
	 */
	private const QUOTE_CHAR_MYSQL = '`';
	
	/**
	 * Mssql quote char
	 */
	private const QUOTE_CHAR_OTHER = '"';
	
	/**
	 * Mutation separator
	 */
	public const MUTATION_SEPARATOR = '_';
	
	/**
	 * Connection constructor.
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}
	
	/**
	 * Create PDO connection
	 * @param string $name
	 * @param string $dsn
	 * @param null|string $user
	 * @param null|string $password
	 * @param int[] $attributes
	 */
	public function connect(string $name, string $dsn, ?string $user = null, ?string $password = null, array $attributes = []): void
	{
		$this->name = $name;
		$parsedDsn = \explode(':', $dsn, 2);
		$this->driver = $parsedDsn[0];
		\parse_str(\str_replace(';', '&', $parsedDsn[1]), $matches);
		
		if ($user !== null) {
			$this->user = $user;
		}
		
		$this->quoteChar = $this->driver === 'mysql' ? self::QUOTE_CHAR_MYSQL : self::QUOTE_CHAR_OTHER;
		$this->attributes = $attributes;
		$this->link = new \PDO($dsn, $user, $password, $attributes);
		
		return;
	}
	
	/**
	 * Get internal name of connection
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
	
	/**
	 * Return PDO object
	 * @return \PDO
	 */
	public function getLink(): \PDO
	{
		if ($this->link === null) {
			throw new GeneralException('Connection is not created');
		}
		
		return $this->link;
	}
	
	/**
	 * Generate 24 chars long uuid
	 * @return string
	 */
	public static function generateUuid(): string
	{
		return \str_replace('.', '', \uniqid('', true) . \rand(10, 99));
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
	 * Set avalailable mutation codes
	 * @param string[] $mutations
	 */
	public function setAvailableMutations(array $mutations): void
	{
		foreach ($mutations as $mutation) {
			if (\strlen($mutation) !== 2) {
				throw new \InvalidArgumentException('Length of language should be 2');
			}
		}
		
		$this->availableMutations = \array_values($mutations);
		$this->mutation = \reset($mutations);
	}
	
	/**
	 * Get available mutations codes
	 * @return string[]
	 */
	public function getAvailableMutations(): array
	{
		return $this->availableMutations;
	}
	
	/**
	 * Generate primary key
	 * @return null|string
	 */
	public function generatePrimaryKey(): ?string
	{
		$generator = $this->primaryKeyGenerator;
		
		return $generator ? (string) $generator() : null;
	}
	
	/**
	 * Get user of current connection
	 * @return null|string
	 */
	public function getUser(): ?string
	{
		return $this->user;
	}
	
	/**
	 * Get database name of current connection
	 * @return null|string
	 */
	public function getDatabaseName(): ?string
	{
		return $this->query('SELECT DATABASE()', [], [], false)->fetchColumn(0);
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
	 * @return string
	 */
	public function getQuoteIdentifierChar(): string
	{
		return $this->quoteChar;
	}
	
	/**
	 * Quote identifier
	 * @param string $string restrict
	 * @return string
	 */
	public function quoteIdentifier(string $string): string
	{
		return $this->quoteChar . $string . $this->quoteChar;
	}
	
	/**
	 * Quote volue, wraps PDO quote
	 * @param string $string restrict
	 * @return string
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
	 * @param bool|null $debug
	 * @return \PDOStatement
	 */
	public function query(string $sql, array $vars = [], array $identifiers = [], ?bool $debug = null): \PDOStatement
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
			$sth->execute($vars);
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
	 * @param string[] $customAnnotations
	 */
	public function setCustomAnnotations(array $customAnnotations): void
	{
		$this->customAnnotations = $customAnnotations;
	}
	
	/**
	 * @return string[]
	 */
	public function getCustomAnnotations(): array
	{
		return $this->customAnnotations;
	}
	
	/**
	 * Get collection of rows
	 * @param string[]|null $from
	 * @param string[] $select
	 * @param string $class
	 * @param mixed[] $classParameters
	 * @param string|null $index
	 * @return \StORM\ICollection
	 */
	public function rows(?array $from = null, array $select = ['*'], string $class = \stdClass::class, array $classParameters = [], ?string $index = null): ICollection
	{
		return new Collection($this, $from, $select, $class, $classParameters, $index);
	}
	
	/**
	 * Create row = insert row into table
	 * @param string $table
	 * @param mixed[]|object $values
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @return \StORM\InsertResult
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
		
		if ($nonAutoincrementPK && !isset($values[$nonAutoincrementPK])) {
			$generatedPrimaryKey = $this->generatePrimaryKey();
			
			if ($generatedPrimaryKey) {
				$values[$nonAutoincrementPK] = $generatedPrimaryKey;
			}
		}
		
		if ($nonAutoincrementPK && isset($values[$nonAutoincrementPK])) {
			$primaryKeys[] = $values[$nonAutoincrementPK];
		}
		
		$sql = $this->getSqlInsert($table, [$values], $vars, [], $ignore);
		
		$beforeId = (int) $this->getLink()->lastInsertId();
		$rowCount = $this->query($sql, $vars)->rowCount();
		
		return new InsertResult($this, $table, false, $ignore, $rowCount, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Create multiple rows at once
	 * @param string $table
	 * @param mixed[][]|object[] $manyValues
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @param int $chunkSize
	 * @return \StORM\InsertResult
	 */
	public function createRows(string $table, array $manyValues, bool $ignore = false, ?string $nonAutoincrementPK = null, int $chunkSize = 100): InsertResult
	{
		$affected = 0;
		$primaryKeys = [];
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		/** @var mixed[]|object $values */
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			if (\is_object($values)) {
				$values = Helpers::toArrayRecursive($values);
			}
			
			if (!\is_array($values)) {
				$type = \gettype($values);
				
				throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
			}
			
			$vars = [];
			
			if ($nonAutoincrementPK && !isset($values[$nonAutoincrementPK])) {
				$generatedPrimaryKey = $this->generatePrimaryKey();
				
				if ($generatedPrimaryKey) {
					$values[$nonAutoincrementPK] = $generatedPrimaryKey;
				}
			}
			
			if ($nonAutoincrementPK && isset($values[$nonAutoincrementPK])) {
				$primaryKeys[] = $values[$nonAutoincrementPK];
			}
			
			$sql = $this->getSqlInsert($table, $values, $vars, [], $ignore);
			
			$affected += $this->query($sql, $vars)->rowCount();
		}
		
		return new InsertResult($this, $table, true, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Synchronize row by unique index, if $columnsToUpdate is null all columns are updated
	 * @param string $table
	 * @param mixed[]|object $values
	 * @param string[]|null $columnsToUpdate
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @return \StORM\InsertResult
	 */
	public function syncRow(string $table, $values, ?array $columnsToUpdate = null, bool $ignore = false, ?string $nonAutoincrementPK = null): InsertResult
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
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		if ($nonAutoincrementPK && !isset($values[$nonAutoincrementPK])) {
			$generatedPrimaryKey = $this->generatePrimaryKey();
			
			if ($generatedPrimaryKey) {
				$values[$nonAutoincrementPK] = $generatedPrimaryKey;
			}
		}
		
		if ($nonAutoincrementPK && isset($values[$nonAutoincrementPK])) {
			$primaryKeys[] = $values[$nonAutoincrementPK];
		}
		
		$sql = $this->getSqlInsert($table, [$values], $vars, $columnsToUpdate, $ignore);
		
		$affected = $this->query($sql, $vars)->rowCount();
		
		return new InsertResult($this, $table, false, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Synchronize rows by unique index, if $columnsToUpdate is null all columns are updated
	 * @param string $table
	 * @param mixed[][]|object[] $manyValues
	 * @param string[]|null $columnsToUpdate
	 * @param bool $ignore
	 * @param string|null $nonAutoincrementPK
	 * @param int $chunkSize
	 * @return \StORM\InsertResult
	 */
	public function syncRows(string $table, array $manyValues, ?array $columnsToUpdate = null, bool $ignore = false, ?string $nonAutoincrementPK = null, int $chunkSize = 100): InsertResult
	{
		$affected = 0;
		$primaryKeys = [];
		$beforeId = (int) $this->getLink()->lastInsertId();
		
		/** @var mixed[]|object $values */
		foreach (\array_chunk($manyValues, $chunkSize) as $values) {
			if (\is_object($values)) {
				$values = Helpers::toArrayRecursive($values);
			}
			
			if (!\is_array($values)) {
				$type = \gettype($values);
				
				throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
			}
			
			$vars = [];
			
			if ($nonAutoincrementPK && !isset($values[$nonAutoincrementPK])) {
				$generatedPrimaryKey = $this->generatePrimaryKey();
				
				if ($generatedPrimaryKey) {
					$values[$nonAutoincrementPK] = $generatedPrimaryKey;
				}
			}
			
			if ($nonAutoincrementPK && isset($values[$nonAutoincrementPK])) {
				$primaryKeys[] = $values[$nonAutoincrementPK];
			}
			
			$sql = $this->getSqlInsert($table, $values, $vars, $columnsToUpdate, $ignore);
			$affected += $this->query($sql, $vars)->rowCount();
		}
		
		return new InsertResult($this, $table, true, $ignore, $affected, $beforeId, (int) $this->getLink()->lastInsertId(), $primaryKeys);
	}
	
	/**
	 * Generate SQL string for insert
	 * @param string $table
	 * @param mixed[][] $manyInserts
	 * @param mixed[] $vars
	 * @param string[]|null $onDuplicateUpdate
	 * @param bool $ignore
	 * @return string
	 */
	public function getSqlInsert(string $table, array $manyInserts, array &$vars, ?array $onDuplicateUpdate, bool $ignore = false): string
	{
		$sql = '';
		$i = 0;
		$noInserts = null;
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
				Helpers::bindVariables($property, $rawValue, $values, $binds, '', (string) $i, $this->getAvailableMutations());
			}
			
			if ($i === 0) {
				$flags = $ignore ? ' IGNORE' : '';
				$sql .= Helpers::createSqlClauseString("INSERT$flags INTO $table", \array_values($binds), ',', '', true);
				$sql .= ' VALUES';
			} else {
				$sql .= ',';
			}
			
			$sql .= Helpers::createSqlClauseString('', \array_keys($binds), ',', '', true);
			$i++;
		}
		
		if ($onDuplicateUpdate === null || $onDuplicateUpdate) {
			$i = 0;
			$sql .= ' ON DUPLICATE KEY UPDATE ';
			
			$onDuplicateUpdate = $onDuplicateUpdate ?: $binds;
			
			foreach (\array_values($onDuplicateUpdate) as $name) {
				if ($i !== 0) {
					$sql .= ',';
				}
				
				$sql .= "$name=VALUES($name)";
				$i++;
			}
		}
		
		$vars = $values;
	
		return $sql;
	}
	
	/**
	 * Get PDO driver
	 * @return string
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
	 * @return int
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
	 * Sleep
	 * @return string[]
	 */
	public function __sleep(): array
	{
		throw new GeneralException('StORM connections are unserializable');
	}
	
	
	/**
	 * @param string $sql
	 * @param mixed[] $vars
	 * @return \StORM\LogItem
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
	 * @return string
	 */
	public function getRealSql(string $sql, array $vars): string
	{
		return \PdoDebugger::show($sql, $vars);
	}
	
	/**
	 * Get last logged item
	 * @return \StORM\LogItem|null
	 */
	public function getLastLogItem(): ?LogItem
	{
		return \end($this->log);
	}
	
	/**
	 * Tells if debug mode is on
	 * @return bool
	 */
	public function isDebug(): bool
	{
		return $this->debug;
	}
	
	/**
	 * Return repository by repository class
	 * @param string $repositoryClass
	 * @return \StORM\Repository
	 * @throws \Nette\DI\MissingServiceException
	 */
	public function getRepository(string $repositoryClass): Repository
	{
		if (!\class_exists($repositoryClass)) {
			throw new NotExistsException(NotExistsException::CLASS_NAME, $repositoryClass);
		}
		
		if (!\is_subclass_of($repositoryClass, Repository::class)) {
			throw new \InvalidArgumentException("$repositoryClass is not child of \StORM\Repository");
		}
		
		/** @var \StORM\Repository $repository */
		$repository = $this->container->getByType($repositoryClass);
		
		return $repository;
	}
	
	/**
	 * Return repository by entity class
	 * @param string $entityClass
	 * @return \StORM\Repository
	 * @throws \Nette\DI\MissingServiceException
	 */
	public function getRepositoryByEntityClass(string $entityClass): Repository
	{
		if (!\class_exists($entityClass)) {
			throw new NotExistsException(NotExistsException::CLASS_NAME, $entityClass);
		}
		
		if (!\is_subclass_of($entityClass, Entity::class)) {
			throw new \InvalidArgumentException("$entityClass is not child of \StORM\Entity");
		}
		
		$repositoryClass = Structure::getRepositoryClassFromEntityClass($entityClass);
		$interface = Structure::getInterfaceFromRepositoryClass($repositoryClass);
		
		/** @var \StORM\Repository $repository */
		$repository = $this->container->getByType(\interface_exists($interface) ? $interface : $repositoryClass);
		
		return $repository;
	}
	
	/**
	 * Get all defined repositories names in container
	 * @return string[]
	 */
	public function getAllRepositories(): array
	{
		return $this->container->findByType(Repository::class);
	}
	
	/**
	 * Set mutation
	 * @param string $mutation
	 */
	public function setMutation(string $mutation): void
	{
		if (!\in_array($mutation, $this->availableMutations)) {
			throw new \InvalidArgumentException("Mutation $mutation is not in available mutations");
		}
		
		$this->mutation = $mutation;
		
		return;
	}
	
	/**
	 * Get current language suffix
	 * @param bool $prefixed
	 * @return string;
	 */
	public function getMutation(bool $prefixed = false): string
	{
		return $prefixed ? self::MUTATION_SEPARATOR . $this->mutation : $this->mutation;
	}
	
	/**
	 * Turn on or turn off debug mode
	 * @param bool $debug
	 */
	public function setDebug(bool $debug): void
	{
		$this->debug = $debug;
	}
}
