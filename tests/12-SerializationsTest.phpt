<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Stock;
use DB\StockRepository;
use Nette\DI\Container;
use StORM\Connection;
use StORM\Exception\GeneralException;
use StORM\Exception\InvalidStateException;
use StORM\Exception\NotExistsException;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class StructuresTest
 * @package Tests
 */
class SerializationsTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	private const STOCK_TABLE = 'stocks_stock';
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testConnection(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		
		Assert::exception(static function () use ($connection): void {
			serialize($connection);
		}, GeneralException::class);
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testSchemaManager(Container $container): void
	{
		/** @var \StORM\SchemaManager $manager */
		$manager = $container->getByType(SchemaManager::class);
		$connection = $manager->getConnection();
		$managerDeserialized = \unserialize(\serialize($manager));
		
		Assert::exception(static function () use ($managerDeserialized): void {
			$managerDeserialized->getConnection();
		}, NotExistsException::class);
		
		Assert::notEqual($managerDeserialized, $manager);
		$managerDeserialized->setConnection($connection);
		Assert::equal($managerDeserialized, $manager);
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testCollection(Container $container)
	{
		$from = [self::STOCK_TABLE];
		
		$connection = $container->getByType(Connection::class);
		
		// unloaded
		$rows = $connection->rows($from)->where('uuid', ['AADR'])->orderBy(['name' => 'ASC'])->setTake(1);
		$rowsDeserialized = \unserialize(\serialize($rows));
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getConnection();
		}, InvalidStateException::class);
		
		Assert::notEqual($rowsDeserialized, $rows);
		$rowsDeserialized->setConnection($connection);
		Assert::equal($rowsDeserialized, $rows);
		
		// loaded
		$rows = $connection->rows($from)->where('uuid', ['AADR'])->orderBy(['name' => 'ASC'])->setTake(1);
		$rows->load();
		$rowsDeserialized = \unserialize(\serialize(clone $rows));
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getConnection();
		}, InvalidStateException::class);
		
		Assert::notEqual($rowsDeserialized, $rows);
		$rowsDeserialized->setConnection($connection);
		
		Assert::notEqual($rowsDeserialized, $rows);
		Assert::true($rows->isLoaded());
		Assert::false($rowsDeserialized->isLoaded());
		
		$rowsDeserialized->load();
		Assert::equal($rowsDeserialized, $rows);
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testRepository(Container $container)
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		
		Assert::exception(static function () use ($stocks): void {
			serialize($stocks);
		}, GeneralException::class);
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testCollectionEntity(Container $container)
	{
		$connection = $container->getByType(Connection::class);
		$stocks = $connection->getRepository(Stock::class);
		
		// unloaded
		$rows = $stocks->many()->where('uuid', ['AADR'])->orderBy(['name' => 'ASC'])->take(1);
		$rowsDeserialized = \unserialize(\serialize(clone $rows));
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getConnection();
		}, NotExistsException::class);
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getRepository();
		}, NotExistsException::class);
		
		Assert::notEqual($rowsDeserialized, $rows);
		$rowsDeserialized->setRepository($stocks);
		var_dump($rowsDeserialized);
		var_dump($rows);
		Assert::equal($rowsDeserialized, $rows);
		
		// loaded
		$rows = $stocks->many()->where('uuid', ['AADR'])->orderBy(['name' => 'ASC'])->take(1);
		$rows->load();
		$rowsDeserialized = \unserialize(\serialize(clone $rows));
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getConnection();
		}, NotExistsException::class);
		
		Assert::exception(static function () use ($rowsDeserialized): void {
			$rowsDeserialized->getRepository();
		}, NotExistsException::class);
		
		Assert::notEqual($rowsDeserialized, $rows);
		$rowsDeserialized->setRepository($stocks);
		
		Assert::notEqual($rowsDeserialized, $rows);
		Assert::true($rows->isLoaded());
		Assert::false($rowsDeserialized->isLoaded());
		$rowsDeserialized->load();
		Assert::equal($rowsDeserialized, $rows);
	}
	
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testEntity(Container $container)
	{
		$connection = $container->getByType(Connection::class);
		$stocks = $connection->getRepository(Stock::class);
		
		$stock = $stocks->one('AAPL');
		$parent = $stock->getParent();
		$stockDeserialized = \unserialize(\serialize(clone $stock));
		
		Assert::exception(static function () use ($stockDeserialized): void {
			$stockDeserialized->getParent();
		}, NotExistsException::class);
		
		Assert::notEqual($stockDeserialized, $stock);
		
		$stockDeserialized->setParent($parent);
		
		Assert::equal($stockDeserialized, $stock);
	}
}

(new SerializationsTest())->run();