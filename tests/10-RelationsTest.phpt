<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Industry;
use DB\IndustryRepository;
use DB\Sector;
use DB\Stock;
use DB\StockRepository;
use Nette\DI\Container;
use StORM\RelationCollection;
use StORM\Connection;
use StORM\Exception\NotFoundException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class RelationsTest
 * @package Tests
 */
class RelationsTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testObject(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		
		$stocks = $connection->getRepository(Stock::class);

		/** @var \DB\Stock $stock */
		$stock = $stocks->one('AAPL');
		
		// get relation
		Assert::type(Sector::class, $stock->sector);
		Assert::equal('electronic-technology', (string) $stock->sector);
		
		// get not existing relation
		/** @var \DB\Stock $stock */
		$stock = $stocks->one('IBM');
		Assert::null($stock->industry);
		
		$stock = $stocks->one('MS');
		Assert::equal(null, $stock->industry);
		
		// not existings
		$stock = $stocks->one('AADR');
		Assert::exception(static function () use ($stock): void {
			$stock->industry;
		}, NotFoundException::class);
		
		$stock = $stocks->one('AAPL');
		Assert::type(RelationCollection::class, $stock->tags);
		
		//Assert::contains('electronic-technology', $stock->tags->toArray('uuid'));
		Assert::contains('telecommunications-equipment', $stock->tags->toArrayOf('uuid'));
		
		$stock->tags->clear();
		$stock->tags->setWhere('this.uuid', 'electronic-technology');
		Assert::contains('electronic-technology', $stock->tags->toArrayOf('uuid'));
		Assert::notContains('telecommunications-equipment', $stock->tags->toArrayOf('uuid'));
		
		
		// nx1
		Assert::type(RelationCollection::class, $stock->alerts);
		Assert::contains('test', $stock->alerts->toArrayOf('uuid'));
		Assert::contains('test2', $stock->alerts->toArrayOf('uuid'));
		
		$stock->alerts->clear();
		$stock->alerts->setWhere('this.uuid', 'test')->toArrayOf('uuid');
		Assert::contains('test', $stock->alerts->toArrayOf('uuid'));
		Assert::notContains('test2', $stock->alerts->toArrayOf('uuid'));
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testObjectModify(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		$connection->setPrimaryKeyGenerator([Connection::class, 'generateUuid']);
		
		$stocks = $connection->getRepository(Stock::class);
		$industries = $connection->getRepository(Industry::class);
		
		/** @var \DB\Stock $stock */
		$stock = $stocks->one('MS');
		
		$stock->tags->relate(['electronic-technology'], true, 'uuid');
		Assert::contains('electronic-technology', $stock->tags->toArrayOf('uuid'));
		
		
		$stock->tags->unrelate(['electronic-technology']);
		Assert::notContains('electronic-technology', $stock->tags->toArrayOf('uuid'));
		
		
		$stock = $stocks->one('MS');
		$stock->tags->relate(['electronic-technology' => ['uuid' => 'test-nxn','value' => 'x']]);
		Assert::same('x', $stock->tags->select(['value' => 'via.value'])['electronic-technology']->value);
		
		// unrelate all tests
		$stock->tags->unrelateAll();
		Assert::count(0, $stock->tags->toArrayOf('uuid'));
		
		
		// n:1
		$stock = $stocks->one('MS');
		$stock->alerts->relate(['test3']);
		Assert::contains('test3', $stock->alerts->toArrayOf('uuid'));
		
		
		$stock2 = $stocks->one('IBM');
		$stock2->alerts->relate(['test3']);
		$stock->alerts->clear();
		Assert::notContains('test3', $stock->alerts->toArrayOf('uuid'));
		
	
		// 1:n
		$stock = $stocks->one('A');
		$stock->industry = $industries->one('telecommunications-equipment');
		$stock->updateAll(['industry']);
		$stock = $stocks->one('A');
		
		Assert::same('telecommunications-equipment', (string) $stock->industry);
		
		$stock->industry = null;
		
		$stock->updateAll(['industry']);
		
		$stock = $stocks->one('A');
		
		Assert::same(null, $stock->industry);
	}
	
	/**
	 * Autojoin
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testAutojoin(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		$stocks = $connection->getRepository(Stock::class);
		$collection = $stocks->many()->setWhere("industry.fk_type = :uuid", ['uuid' => 'energy']);
		$collection->load();
		Assert::count(1, $collection->getModifiers()['JOIN']);
	}
}

(new RelationsTest())->run();
