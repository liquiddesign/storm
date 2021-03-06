<?php

declare(strict_types=1);

namespace StORM;

use StORM\Exception\InvalidStateException;

class InsertResult
{
	/**
	 * PDO rowCount return 2 if row is updated
	 */
	public const UPDATE_AFFECTED_COUNT = 2;
	
	/**
	 * PDO rowCount return 1 if row is inserted
	 */
	public const INSERT_AFFECTED_COUNT = 1;
	
	private ?\StORM\Connection $connection;
	
	private int $rowCount;
	
	private int $idBefore;
	
	private ?int $idAfter;
	
	/**
	 * @var string[]
	 */
	private array $primaryKeys;
	
	private string $tableName;
	
	private bool $multiple;
	
	private bool $ignore;
	
	/**
	 * Literal constructor.
	 * @param \StORM\Connection $connection
	 * @param string $tableName
	 * @param bool $multiple
	 * @param bool $ignore
	 * @param int $rowCount
	 * @param int $idBefore
	 * @param int|null $idAfter
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
			throw new InvalidStateException(null, InvalidStateException::SYNCED);
		}
		
		return $this->rowCount === self::UPDATE_AFFECTED_COUNT;
	}
	
	public function getLastInsertedId(): ?int
	{
		return $this->idAfter;
	}
	
	/**
	 * @return string[]|int[]
	 */
	public function getPrimaryKeys(): array
	{
		if (!$this->primaryKeys) {
			if ($this->ignore && $this->multiple) {
				throw new InvalidStateException(null, InvalidStateException::IGNORE);
			}
			
			if ($this->idBefore !== $this->idAfter) {
				return \range($this->idAfter - $this->rowCount, $this->idAfter - 1);
			}
		}
		
		return $this->primaryKeys;
	}
	
	public function getRows(string $primaryKeyName): ICollection
	{
		return $this->connection->rows([$this->tableName])->setWhere($primaryKeyName, $this->getPrimaryKeys());
	}
}
