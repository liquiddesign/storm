<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use StORM\Literal;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class UpdateDeleteCollectionTest
 * @package Tests
 */
class UpdateDeleteCollectionTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	private const STOCK_TABLE = 'stocks_stock';
	
	/**
	 * Update
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testUpdate(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		
		// 1. update value
		$storm->rows($from)->setWhere('uuid', 'AAPL')->update(['beta' => 2.5]);
		$stock = $storm->rows($from)->setWhere('uuid', 'AAPL')->first();
		Assert::same(2.5, (float) $stock->beta);
		$storm->rows($from)->setWhere('uuid', 'AAPL')->update(['beta' => 3.0]);
		$stock = $storm->rows($from)->setWhere('uuid', 'AAPL')->first();
		Assert::same(3.0, (float) $stock->beta);
		
		// 2. update literal
		$storm->rows($from)->setWhere('uuid', 'AAPL')->update(['beta' => new Literal('beta + 1.5')]);
		$stock = $storm->rows($from)->setWhere('uuid', 'AAPL')->first();
		Assert::same(4.5, (float) $stock->beta);
	}
	
	/**
	 * Delete
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testDelete(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = 'stocks_tag';
		$from = [$table];
		
		$prevCount = $storm->rows($from)->count();
		
		$storm->createRows($table, [['uuid' => 'delete1','name' => 'John Doe'], ['uuid' => 'delete2','name' => 'Jane Doe'], ['uuid' => 'delete3','name' => 'Jane Doe']]);
		Assert::equal($prevCount + 3, $storm->rows($from)->count());
		
		$storm->rows($from)->setWhere('uuid', ['delete3'])->delete();
		Assert::equal($prevCount + 2, $storm->rows($from)->count());
		
		$storm->rows($from)->setWhere('uuid', ['delete1', 'delete2'])->delete();
		Assert::equal($prevCount, $storm->rows($from)->count());
	}
}

(new UpdateDeleteCollectionTest())->run();
