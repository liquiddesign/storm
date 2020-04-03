<?php

namespace StORM;

use StORM\Exception\InvalidStateException;

class InsertResult
{
	/**
	 * @var \StORM\Connection
	 */
	private $connection;
	
	/**
	 * @var int
	 */
	private $rowCount;
	
	/**
	 * @var int
	 */
	private $idBefore;
	
	/**
	 * @var int
	 */
	private $idAfter;
	
	/**
	 * @var string[]
	 */
	private $primaryKeys;
	
	/**
	 * @var string
	 */
	private $tableName;
	
	/**
	 * @var bool
	 */
	private $multiple;
	
	/**
	 * @var bool
	 */
	private $ignore;
	
	/**
	 * Literal constructor.
	 * @param \StORM\Connection $connection
	 * @param string $tableName
	 * @param bool $multiple
	 * @param bool $ignore
	 * @param int $rowCount
	 * @param int $idBefore
	 * @param int $idAfter
	 * @param string[] $primaryKeys
	 */
	public function __construct(Connection $connection, string $tableName, bool $multiple, bool $ignore, int $rowCount, int $idBefore, ?int $idAfter, array $primaryKeys)
	{
		$this->connection = $connection;
		$this->rowCount = $rowCount;
		$this->idBefore = $idBefore;
		$this->idAfter = $idAfter;
		$this->primaryKeys = $primaryKeys;
		$this->tableName = $tableName;
		$this->multiple = $multiple;
		$this->ignore = $ignore;
	}
	
	public function getRowCount(): int
	{
		return $this->rowCount;
	}
	
	public function isSynced(): bool
	{
		if ($this->multiple) {
			throw new InvalidStateException(InvalidStateException::SYNCED);
		}
		
		return $this->rowCount === 2;
	}
	
	public function getLastInsertedId(): ?int
	{
		return $this->idAfter;
	}
	
	/**
	 * @return string[]
	 */
	public function getPrimaryKeys(): array
	{
		if (!$this->primaryKeys) {
			if ($this->ignore === null && $this->multiple) {
				throw new InvalidStateException(InvalidStateException::IGNORE);
			}
			
			if ($this->idBefore !== $this->idAfter) {
				return \range($this->idAfter - $this->rowCount, $this->idAfter - 1);
			}
		}
		
		return $this->primaryKeys;
	}
	
	public function getRows(string $primaryKeyName): ICollection
	{
		return $this->connection->rows([$this->tableName])->where($primaryKeyName, $this->getPrimaryKeys());
	}
}
