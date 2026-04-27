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

	protected int $exportFilesLimit;

	public function __construct(\StORM\Connection $db, string $name, int $panelLimit = 50, int $exportFilesLimit = 50)
	{
		$this->name = $name;
		$this->db = $db;
		$this->panelLimit = $panelLimit;
		$this->exportFilesLimit = $exportFilesLimit;
	}

	/**
	 * Compute all aggregated data in a single pass + two sorts + file export
	 * @return array{
	 *     totalTime: float,
	 *     totalAmount: int,
	 *     uniqueCount: int,
	 *     errorCount: int,
	 *     slowest: array<\StORM\LogItem>,
	 *     frequent: array<\StORM\LogItem>,
	 *     errors: array<\StORM\LogItem>,
	 *     exportPath: string|null
	 * }
	 */
	public function getAggregated(): array
	{
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

		return [
			'totalTime' => $totalTime,
			'totalAmount' => $totalAmount,
			'uniqueCount' => \count($log),
			'errorCount' => $errorCount,
			'slowest' => \array_slice($byTime, 0, $this->panelLimit),
			'frequent' => \array_slice($byAmount, 0, $this->panelLimit),
			'errors' => $errors,
			'exportPath' => $this->exportFullLog($log),
		];
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
	 * Export full query log to JSON file
	 * @param array<\StORM\LogItem> $log
	 */
	public function exportFullLog(array $log): string|null
	{
		if (\count($log) === 0) {
			return null;
		}

		$logDir = \Tracy\Debugger::$logDirectory;

		if ($logDir === null) {
			return null;
		}

		$requestId = \Nette\Utils\Strings::substring(\md5(\uniqid('', true)), 0, 8);
		$filename = "storm-queries-{$this->name}-{$requestId}.json";
		$filepath = $logDir . '/' . $filename;

		$this->pruneOldExports($logDir);

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
	 * Substitute bound variables (`:name`) in SQL with their literal values.
	 * Sorts placeholder keys by length DESC so that `:__var20380` is replaced
	 * before `:__var2038` and prefix collisions don't corrupt the output.
	 * @param array<mixed> $vars
	 */
	public static function interpolateSql(string $sql, array $vars): string
	{
		if (\count($vars) === 0) {
			return $sql;
		}

		$keys = \array_keys($vars);
		\usort($keys, static fn(string|int $a, string|int $b): int => \strlen((string) $b) <=> \strlen((string) $a));

		foreach ($keys as $key) {
			$placeholder = \str_starts_with((string) $key, ':') ? (string) $key : ':' . $key;
			$sql = \str_replace($placeholder, self::formatLiteral($vars[$key]), $sql);
		}

		return $sql;
	}

	/**
	 * Format a PHP value as a SQL literal for the copy-to-clipboard panel.
	 */
	public static function formatLiteral(mixed $value): string
	{
		if ($value === null) {
			return 'NULL';
		}

		if (\is_bool($value)) {
			return $value ? '1' : '0';
		}

		if (\is_int($value) || \is_float($value)) {
			return (string) $value;
		}

		if ($value instanceof \BackedEnum) {
			return self::formatLiteral($value->value);
		}

		if ($value instanceof \DateTimeInterface) {
			return "'" . $value->format('Y-m-d H:i:s') . "'";
		}

		if (\is_array($value)) {
			return '(' . \implode(', ', \array_map(self::formatLiteral(...), $value)) . ')';
		}

		$string = (string) $value;

		return "'" . \str_replace(['\\', "'"], ['\\\\', "''"], $string) . "'";
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

	/**
	 * Keep only the $exportFilesLimit newest export files, delete older ones
	 */
	protected function pruneOldExports(string $logDir): void
	{
		if ($this->exportFilesLimit <= 0) {
			return;
		}

		$pattern = $logDir . '/storm-queries-' . $this->name . '-*.json';
		$files = \glob($pattern);

		if ($files === false || \count($files) <= $this->exportFilesLimit) {
			return;
		}

		\usort($files, static fn(string $a, string $b): int => \filemtime($b) <=> \filemtime($a));

		foreach (\array_slice($files, $this->exportFilesLimit) as $old) {
			try {
				\Nette\Utils\FileSystem::delete($old);
			} catch (\Nette\IOException) {
				// race condition — another request already deleted it
			}
		}
	}
}
