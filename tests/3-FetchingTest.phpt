<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use StORM\Exception\InvalidStateException;
use StORM\Exception\NotFoundException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class FetchingTest
 * @package Tests
 */
class FetchingTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	private const STOCK_TABLE = 'stocks_stock';
	
	/**
	 * Testing manual loading and foreach dynamic loading
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testLoading(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$from = [self::STOCK_TABLE];
		
		// 1. foreach testings
		$collection = $storm->rows($from)->setWhere('uuid', 'AAPL');
		
		for ($j = 0; $j !== 2; $j++) {
			$i = 0;
			Assert::equal(false, $collection->isLoaded());
			
			foreach ($collection as $index => $stock) {
				Assert::equal(0, (int) $index);
				Assert::equal('AAPL', $stock->uuid);
				Assert::equal('Apple Inc.', $stock->name);
				$i++;
			}
			
			Assert::equal(true, $collection->isLoaded());
			Assert::equal(true, $i > 0);
			
			$collection->clear();
		}
		
		// 2. Manual loading
		$collection =  $storm->rows($from);
		Assert::equal(false, $collection->isLoaded());
		$collection->load();
		Assert::equal(true, $collection->isLoaded());
		/** @var \Countable $collection */
		Assert::equal(true, \count($collection) > 0);
		$collection->load();
	}
	
	/**
	 * Fetching in while
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testFetching(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$from = [self::STOCK_TABLE];
		
		$collection = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->setOrderBy(['uuid']);
		
		// 1. while fetching
		$i = 0;
		
		while ($stock = $collection->fetch()) {
			if ($i === 0) {
				Assert::equal('AAPL', $stock->uuid);
				Assert::equal('Apple Inc.', $stock->name);
			}
			
			if ($i === 1) {
				Assert::equal('IBM', $stock->uuid);
				Assert::equal('International Business Machines Corporation', $stock->name);
			}
			
			$i++;
		}
		
		Assert::equal(false, $collection->isLoaded());
		Assert::equal(2, $i);
		
		// 2. fetching on loaded collection
		$collection = $storm->rows($from);
		$collection->load();
		
		Assert::exception(static function () use ($collection): void {
			$collection->fetch();
		}, InvalidStateException::class);
		
		$collection->clear();
		Assert::notNull($collection->fetch());
	}
	
	/**
	 * Getting single object and using index
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testSingleObject(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$from = [self::STOCK_TABLE];
		
		// 1. getting record by first method
		$object = $storm->rows($from)->setWhere('uuid', 'AAPL')->first();
		Assert::type(\stdClass::class, $object);
		Assert::same('AAPL', $object->uuid);
		
		// 2. getting record by invalid offset
		Assert::exception(static function () use ($storm, $from): void {
			$storm->rows($from)['NOT-FOUND'];
		}, NotFoundException::class);
		
		// 3. getting record by offset
		$collection = $storm->rows($from)->setIndex('uuid');
		Assert::equal(false, $collection->isLoaded());
		$object = $collection['AAPL'];
		Assert::type(\stdClass::class, $object);
		Assert::same('AAPL', $object->uuid);
		Assert::equal(true, $collection->isLoaded());
		
		// 4. getting record by offset with condition
		$object = $storm->rows($from)->setWhere('uuid', 'AAPL')[0];
		Assert::type(\stdClass::class, $object);
		Assert::same('AAPL', $object->uuid);
	}
	
	/**
	 * Getting single value
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testValue(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$from = [self::STOCK_TABLE];
		
		Assert::equal('AAPL', $storm->rows($from)->setWhere('uuid', 'AAPL')->firstValue('uuid'));
		Assert::equal('Apple Inc.', $storm->rows($from)->setWhere('uuid', 'AAPL')->firstValue('name'));
		Assert::equal('AAPL', $storm->rows($from)->setWhere('uuid', 'AAPL')->getPDOStatement()->fetchColumn(0));
		Assert::equal('Apple Inc.', $storm->rows($from)->setWhere('uuid', 'AAPL')->getPDOStatement()->fetchColumn(1));
	}
}

(new FetchingTest())->run();
