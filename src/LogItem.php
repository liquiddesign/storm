<?php

declare(strict_types=1);

namespace StORM;

class LogItem
{
	private int $amount = 1;
	
	/**
	 * @var mixed[]
	 */
	private array $vars;
	
	private string $sql;
	
	private float $totalTime = 0.0;
	
	private bool $error = false;
	
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
	 */
	public function getSql(): string
	{
		return $this->sql;
	}
	
	/**
	 * Get total execution time
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
