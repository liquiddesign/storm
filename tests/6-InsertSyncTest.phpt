<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use StORM\Literal;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class InsertSyncTest
 * @package Tests
 */
class InsertSyncTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	private const TEST_TABLE = 'stocks_test';
	
	/**
	 * @param int $number
	 * @param string $flag
	 * @return string[]
	 */
	private function generateMockData(int $number, string $flag): array
	{
		$data = [];
		
		for ($i = 0; $i !== $number; $i++) {
			$data[$i] = ['uuid' => "uuid$i-$flag",'name' => "name$i", 'test' => "test$i", 'flag' => $flag];
		}
		
		return $data;
	}
	
	/**
	 * @param \StORM\LogItem[] $log
	 * @return int
	 */
	private function getTotalQueries(array $log): int
	{
		$totalAmount = 0;
		
		foreach ($log as $item) {
			$totalAmount += $item->getAmount();
		}
		
		return $totalAmount;
	}
	
	/**
	 * Insert single row
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testInsertRow(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::TEST_TABLE;
		$flag = \basename(__FUNCTION__);
		$data = $this->generateMockData(2, $flag);
		
		// wipe
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		
		// 1. insert
		$affected = $storm->createRow($table, $data[0], false, 'uuid');
		Assert::same(1, $affected->getRowCount());
		Assert::same(1, $affected->getRows('uuid')->enum());
		Assert::same(1, $storm->rows([$table])->setWhere('flag', $flag)->count());
		
		Assert::exception(static function () use ($storm, $table, $data): void {
			$storm->createRow($table, $data[0]);
		}, \PDOException::class);
		
		// 2. insert and ingnore
		$affected = $storm->createRow($table, $data[0], true);
		Assert::same(0, $affected->getRowCount());
		$affected = $storm->createRow($table, $data[1], true);
		Assert::same(1, $affected->getRowCount());
		
		Assert::same(2, $storm->rows([$table])->setWhere('flag', $flag)->count());
		
		// 3. insert literal
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		$affected = $storm->createRow($table, \array_merge($data[0], ['test' => new Literal("UPPER(uuid)")]));
		
		Assert::same(1, $affected->getRowCount());
		$row = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])[0];
		Assert::same(\strtoupper($row->uuid), $row->test);
	}
	
	/**
	 * Insert multiple rows
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testInsertRows(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::TEST_TABLE;
		$flag = \basename(__FUNCTION__);
		$data = $this->generateMockData(3, $flag);
		
		// wipe
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		
		// 1. insert
		$affected = $storm->createRows($table, $data);
		Assert::same(3, $affected->getRowCount());
		Assert::same(3, $storm->rows([$table])->setWhere('flag', $flag)->count());
		
		// 2. insert ignore
		$data = $this->generateMockData(4, $flag);
		Assert::exception(static function () use ($storm, $table, $data): void {
			$storm->createRows($table, $data);
		}, \PDOException::class);
		
		$affected = $storm->createRows($table, $data, true);
		Assert::same(1, $affected->getRowCount());
		Assert::same(4, $storm->rows([$table])->setWhere('flag', $flag)->count());
		
		
		// 3. using chunk
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		$data = $this->generateMockData(9, $flag);
		$total = $this->getTotalQueries($storm->getLog());
		$affected = $storm->createRows($table, $data, false, null, 2);
		
		Assert::same(9, $affected->getRowCount());
		Assert::same(5, $this->getTotalQueries($storm->getLog()) - $total);
	}
	
	/**
	 * Sync single rows
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testSyncRow(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::TEST_TABLE;
		$flag = \basename(__FUNCTION__);
		$data = $this->generateMockData(3, $flag);
		
		
		// 1. sync all
		// prepare row
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		$storm->createRow($table, $data[0]);
		$row = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		Assert::same($data[0], $row);
		
		// update if exists
		$modifiedData = $data[0];
		$modifiedData['name'] = 'altered1';
		$modifiedData['test'] = 'altered2';
		$storm->syncRow($table, $modifiedData);
		$row = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		Assert::notSame($data[0], $row);
		Assert::same('altered1', $row['name']);
		Assert::same('altered2', $row['test']);
		
		// 2, sync all and ingnore
		$modifiedData = $data[0];
		$modifiedData['not-exists'] = 'altered1';
		$storm->syncRow($table, $data[0], null, true);
		
		
		// 2, sync selected
		// prepare row
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		$storm->createRow($table, $data[0]);
		$row = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		Assert::same($data[0], $row);
		// update if exists
		$modifiedData = $data[0];
		$modifiedData['name'] = 'altered1';
		$modifiedData['test'] = 'altered2';
		$storm->syncRow($table, $modifiedData, ['test']);
		$row = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		Assert::notSame($data[0], $row);
		Assert::same('name0', $row['name']);
		Assert::same('altered2', $row['test']);
	}
	
	/**
	 * Sync multiple rows
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testSyncRows(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::TEST_TABLE;
		$flag = \basename(__FUNCTION__);
		
		// wipe
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		
		// 1. sync all
		$data = $this->generateMockData(3, $flag);
		$aux = $this->generateMockData(4, $flag);
		$storm->createRows($table, $data);
		$dataBefore = $data;
		$data[0]['name'] = 'altered1';
		$data[0]['test'] = 'altered2';
		$data[1]['name'] = 'altered3';
		$data[1]['test'] = 'altered4';
		
		$affected = $storm->syncRows($table, \array_merge($data, [$aux[3]]));
		Assert::same(5, $affected->getRowCount());
		$row0 = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		$row1 = $storm->rows([$table])->setWhere('uuid', $data[1]['uuid'])->jsonSerialize()[0];
		$row2 = $storm->rows([$table])->setWhere('uuid', $data[2]['uuid'])->jsonSerialize()[0];
		Assert::notSame($dataBefore[0], $row0);
		Assert::notSame($dataBefore[1], $row1);
		Assert::same($dataBefore[2], $row2);
		Assert::same('altered1', $row0['name']);
		Assert::same('altered2', $row0['test']);
		Assert::same('altered3', $row1['name']);
		Assert::same('altered4', $row1['test']);
		Assert::same('name2', $row2['name']);
		Assert::same('test2', $row2['test']);
		
		// wipe
		$storm->rows([$table])->setWhere('flag', $flag)->delete();
		
		// 2, sync selected
		$data = $this->generateMockData(3, $flag);
		$storm->createRows($table, $data);
		$dataBefore = $data;
		$data[0]['name'] = 'altered1';
		$data[0]['test'] = 'altered2';
		$data[1]['name'] = 'altered3';
		$data[1]['test'] = 'altered4';
		$affected = $storm->syncRows($table, $data, ['name']);
		Assert::same(4, $affected->getRowCount());
		$row0 = $storm->rows([$table])->setWhere('uuid', $data[0]['uuid'])->jsonSerialize()[0];
		$row1 = $storm->rows([$table])->setWhere('uuid', $data[1]['uuid'])->jsonSerialize()[0];
		$row2 = $storm->rows([$table])->setWhere('uuid', $data[2]['uuid'])->jsonSerialize()[0];
		Assert::notSame($dataBefore[0], $row0);
		Assert::notSame($dataBefore[1], $row1);
		Assert::same($dataBefore[2], $row2);
		Assert::same('altered1', $row0['name']);
		Assert::same('test0', $row0['test']);
		Assert::same('altered3', $row1['name']);
		Assert::same('test1', $row1['test']);
		Assert::same('name2', $row2['name']);
		Assert::same('test2', $row2['test']);
	}
}

(new InsertSyncTest())->run();
