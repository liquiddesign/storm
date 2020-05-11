<?php

declare(strict_types=1);

namespace StORM;

class LogItem
{
	/**
	 * @var int
	 */
	private $amount = 1;
	
	/**
	 * @var mixed[]
	 */
	private $vars = [];
	
	/**
	 * @var string
	 */
	private $sql = '';
	
	/**
	 * @var float
	 */
	private $totalTime = 0.0;
	
	/**
	 * @var bool
	 */
	private $error = false;
	
	/**
	 * LogItem constructor.
	 * @param string $sql
	 * @param mixed[] $vars
	 */
	public function __construct(string $sql, array $vars = [])
	{
		$this->sql = $sql;
		$this->vars = $vars;
	}
	
	/**
	 * Get amount of SQL items
	 * @return int
	 */
	public function getAmount(): int
	{
		return $this->amount;
	}
	
	/**
	 * Get bindend variables
	 * @return mixed[]
	 */
	public function getVars(): array
	{
		return $this->vars;
	}
	
	/**
	 * Get SQL expression
	 * @return string
	 */
	public function getSql(): string
	{
		return $this->sql;
	}
	
	/**
	 * Get total execution time
	 * @return float
	 */
	public function getTotalTime(): float
	{
		return $this->totalTime;
	}
	
	/**
	 * Add time to total time
	 * @param float $time
	 */
	public function addTime(float $time): void
	{
		$this->totalTime += $time;
	}
	
	/**
	 * Tells if SQL query ends with error
	 * @return bool
	 */
	public function hasError(): bool
	{
		return $this->error;
	}
	
	/**
	 * Set binded vars
	 * @param mixed[] $vars
	 */
	public function setVars(array $vars): void
	{
		$this->vars = $vars;
	}
	
	/**
	 * Set amount of queries
	 * @param int $amount
	 */
	public function setAmount(int $amount): void
	{
		$this->amount = $amount;
	}
	
	/**
	 * Set if content contains error or not
	 * @param bool $error
	 */
	public function setError(bool $error): void
	{
		$this->error = $error;
	}
}
