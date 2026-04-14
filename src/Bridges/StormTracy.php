<?php

declare(strict_types = 1);

namespace StORM\Bridges;

/**
 * @template T of object
 */
class StormTracy implements \Tracy\IBarPanel
{
	use \Nette\SmartObject;

	protected \StORM\Connection $db;

	/**
	 * Name of connection
	 */
	protected string $name;

	protected int $panelLimit;

	/**
	 * @var array{totalTime: float, totalAmount: int, uniqueCount: int, errorCount: int, slowest: array<\StORM\LogItem>, frequent: array<\StORM\LogItem>, errors: array<\StORM\LogItem>}|null
	 */
	private array|null $aggregated = null;

	public function __construct(\StORM\Connection $db, string $name, int $panelLimit = 50)
	{
		$this->name = $name;
		$this->db = $db;
		$this->panelLimit = $panelLimit;
	}

	/**
	 * Compute all aggregated data in a single pass + two sorts
	 * @return array{totalTime: float, totalAmount: int, uniqueCount: int, errorCount: int, slowest: array<\StORM\LogItem>, frequent: array<\StORM\LogItem>, errors: array<\StORM\LogItem>}
	 */
	public function getAggregated(): array
	{
		if ($this->aggregated !== null) {
			return $this->aggregated;
		}

		$log = $this->db->getLog();
		$totalTime = 0.0;
		$totalAmount = 0;
		$errorCount = 0;
		$errors = [];

		foreach ($log as $item) {
			$totalTime += $item->getTotalTime();
			$totalAmount += $item->getAmount();

			if (!$item->hasError()) {
				continue;
			}

			$errorCount++;
			$errors[] = $item;
		}

		$byTime = $log;
		\usort($byTime, static fn(\StORM\LogItem $a, \StORM\LogItem $b): int => $b->getTotalTime() <=> $a->getTotalTime());

		$byAmount = $log;
		\usort($byAmount, static fn(\StORM\LogItem $a, \StORM\LogItem $b): int => $b->getAmount() <=> $a->getAmount());

		$this->aggregated = [
			'totalTime' => $totalTime,
			'totalAmount' => $totalAmount,
			'uniqueCount' => \count($log),
			'errorCount' => $errorCount,
			'slowest' => \array_slice($byTime, 0, $this->panelLimit),
			'frequent' => \array_slice($byAmount, 0, $this->panelLimit),
			'errors' => $errors,
		];

		return $this->aggregated;
	}

	public function getTotalTime(): float
	{
		return $this->getAggregated()['totalTime'];
	}

	public function getTotalQueries(): int
	{
		return $this->getAggregated()['totalAmount'];
	}

	/**
	 * Export full query log to JSON file (called on demand, not during render)
	 */
	public function exportFullLog(): string|null
	{
		$log = $this->db->getLog();

		if (\count($log) === 0) {
			return null;
		}

		$logDir = \Tracy\Debugger::$logDirectory;

		if ($logDir === null) {
			return null;
		}

		$sessionId = \session_id() ?: 'nosession';
		$filename = "storm-queries-{$this->name}-{$sessionId}.json";
		$filepath = $logDir . '/' . $filename;

		$handle = \fopen($filepath, 'w');

		if ($handle === false) {
			return null;
		}

		\fwrite($handle, "[\n");
		$first = true;

		foreach ($log as $item) {
			if (!$first) {
				\fwrite($handle, ",\n");
			}

			$json = \json_encode([
				'sql' => $item->getSql(),
				'vars' => $item->getVars(),
				'time' => $item->getTotalTime(),
				'amount' => $item->getAmount(),
				'error' => $item->hasError(),
				'location' => $item->getLocation(),
			], \JSON_UNESCAPED_UNICODE);

			\fwrite($handle, $json !== false ? $json : '{}');
			$first = false;
		}

		\fwrite($handle, "\n]");
		\fclose($handle);

		return $filepath;
	}

	/**
	 * Renders HTML code for storm panel
	 * @throws \Throwable
	 */
	public function getTab(): string
	{
		return self::capture(function (): void { // @codingStandardsIgnoreLine
			require __DIR__ . '/templates/Storm.panel.tab.phtml';
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

			return (string) \ob_get_clean();
		} catch (\Throwable $e) {
			\ob_end_clean();

			throw $e;
		}
	}
}
