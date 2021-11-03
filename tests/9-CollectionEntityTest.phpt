<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Sector;
use DB\Stock;
use DB\StockRepository;
use DB\Type;
use DB\TypeRepository;
use Nette\DI\Container;
use StORM\Connection;
use StORM\Exception\NotExistsException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class CollectionEntityTest
 * @package Tests
 */
class CollectionEntityTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Prefetched Relation
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testPrefetchedRelation(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		
		$type = $types->many()->setFrom(['this' => 'stocks_type2'])->select($types->getRelationSelect('sector'))->setWhere('this.id', 'id-0')->first();
		$count = \count($storm->getLog());
		Assert::type(Sector::class, $type->sector);
		Assert::same('energy', $type->sector->uuid);
		Assert::same($count, \count($storm->getLog()));
	}
	
	/**
	 * Possible values
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testPossibleValues(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		$collection = $stocks->many()->setWhere('uuid', ['AAPL', 'IBM']);
		Assert::same(['AAPL', 'IBM'], $collection->getPossibleValues('uuid'));
		$collection = $stocks->many()->setWhere('uuid', 'AAPL');
		Assert::same(['AAPL'], $collection->getPossibleValues('uuid'));
		$collection = $stocks->many()->setWhere('uuid', 'AAPL')->where('name', 'test');
		Assert::same([], $collection->getPossibleValues('uuid'));
	}
	
	/**
	 * Filter
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testFilter(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		$uuid = $stocks->filter($stocks->many(), ['id' => 'AAPL'])->firstValue('uuid');
		Assert::same('AAPL', $uuid);
		
		Assert::exception(static function () use ($stocks): void {
			$stocks->filter($stocks->many(),['not-exists' => 'AAPL'])->firstValue('uuid');
		}, \InvalidArgumentException ::class);
			
		
		$uuid = $stocks->filter($stocks->many(), ['id' => 'AAPL', 'not-exists' => 'AAPL'], true)->firstValue('uuid');
		Assert::same('AAPL', $uuid);
	}
	
	/**
	 * Loop Optimalization
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testLoopOptimalization(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		
		// turn on loops optimalization
		foreach ($types->many()->setFrom(['this' => 'stocks_type2']) as $type) {
			Assert::type(Sector::class, $type->sector);
			Assert::same('energy', $type->sector->uuid);
		}
		
		Assert::same(1, $storm->getLastLogItem()->getAmount());
		
		// turn off loops optimalization
		foreach ($types->many()->setOptimization(false)->setFrom(['this' => 'stocks_type2']) as $type) {
			Assert::type(Sector::class, $type->sector);
			Assert::same('energy', $type->sector->uuid);
		}
		
		// not same because of binder id
		// Assert::same(3, $storm->getLastLogItem()->getAmount());
	}
}

(new CollectionEntityTest())->run();
