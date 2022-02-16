<?php

declare(strict_types = 1);

namespace StORM\Bridges;

class StormTracy implements \Tracy\IBarPanel
{
	use \Nette\SmartObject;
	
	/**
	 * Stm service
	 */
	protected \StORM\Connection $db;
	
	/**
	 * Name of connection
	 */
	protected string $name;
	
	/**
	 * Construct new panel
	 * @param \StORM\Connection $db
	 * @param string $name
	 */
	public function __construct(\StORM\Connection $db, string $name)
	{
		$this->name = $name;
		$this->db = $db;
		
		return;
	}
	
	public function getTotalTime(): float
	{
		$totalTime = 0.0;
		
		foreach ($this->db->getLog() as $item) {
			$totalTime += $item->getTotalTime();
		}
		
		return $totalTime;
	}
	
	public function getTotalQueries(): int
	{
		$totalAmount = 0;
		
		foreach ($this->db->getLog() as $item) {
			$totalAmount += $item->getAmount();
		}
		
		return $totalAmount;
	}
	
	/**
	 * Renders HTML code for storm panel
	 * @throws \Throwable
	 */
	public function getTab(): string
	{
		return self::capture(function (): void { // @codingStandardsIgnoreLine
			require __DIR__ . '/templates/Storm.panel.tab.phtml';
			
			return;
		});
	}
	
	/**
	 * Get Storm panel
	 * @throws \Throwable
	 */
	public function getPanel(): string
	{
		return self::capture(function (): void {  // @codingStandardsIgnoreLine
			require __DIR__ . '/templates/Storm.panel.phtml';
			
			return;
		});
	}
	
	/**
	 * Captures PHP output into a string.
	 * @param callable $func
	 * @throws \Throwable
	 */
	public static function capture(callable $func): string
	{
		\ob_start();
		
		try {
			$func();
			
			return \ob_get_clean();
		} catch (\Throwable $e) {
			\ob_end_clean();
			
			throw $e;
		}
	}
}
