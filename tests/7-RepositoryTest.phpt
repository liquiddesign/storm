<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Sector;
use DB\SectorRepository;
use DB\Stock;
use DB\StockRepository;
use DB\Type;
use DB\TypeRepository;
use Nette\DI\Container;
use StORM\Collection;
use StORM\Connection;
use StORM\Exception\NotExistsException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Structure;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class RepositoryTest
 * @package Tests
 */
class RepositoryTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Getting repositories
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testGetting(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks2 = $storm->getRepository(Stock::class);
		$stocks3 = $container->getService('db.stocks');
		
		Assert::type(StockRepository::class, $stocks2);
		Assert::type(StockRepository::class, $stocks3);
		Assert::same($stocks2, $stocks3);
	}
	
	/**
	 * Getting default metadata
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testMetadata(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		$alias = TypeRepository::DEFAULT_ALIAS;
		
		Assert::type(Structure::class, $types->getStructure());
		Assert::type(SchemaManager::class, $types->getSchemaManager());
		Assert::same(Type::class, $types->getEntityClass());
		Assert::same($storm, $types->getConnection());
		Assert::same([$alias => 'stocks_type'], $types->getDefaultFrom());
		Assert::same([
			'id' => "$alias.id",
			'myName' => "$alias.name",
			'fk_sector' => "$alias.fk_sector",
			], $types->getDefaultSelect());
	}
	
	/**
	 * Getting collection and entity
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testObjects(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		$types = $storm->getRepository(Type::class);
		$sectors = $storm->getRepository(Sector::class);
		
		Assert::type(Stock::class, $stocks->one('AAPL'));
		Assert::type(Stock::class, $stocks->one(['uuid' => 'AAPL']));
		Assert::same(null, $stocks->one(['uuid' => 'NOT_EXISTS']));
		
		Assert::exception(static function () use ($stocks): void {
			$stocks->one(['uuid' => 'NOT_EXISTS'], true);
		}, NotFoundException::class);
		
		/** @var \StORM\Collection $collection */
		$collection = $types->many();
		Assert::type(Collection::class, $collection);
		Assert::same($types, $collection->getRepository());
		Assert::same($types->getDefaultFrom(), $collection->getModifiers()['FROM']);
		Assert::same($types->getDefaultSelect(), $collection->getModifiers()['SELECT']);
		
		$params = [];
		$class = $collection->getFetchClass($params);
		Assert::same(Type::class, $class);
		Assert::same([[], $collection, [], null], $params);
		
		$params = [];
		$collection = $sectors->many();
		$class = $collection->getFetchClass($params);
		Assert::same(Sector::class, $class);
		$lang = $storm->getMutation();
		$availableLanguages = $storm->getAvailableMutations();
		Assert::same([[], $collection, $availableLanguages, $lang], $params);
	}
	
	/**
	 * Create One
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testCreateOne(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		$id = 'test-create-one';
		
		// 1. standart creating and maping property to column
		$types->many()->delete();
		$object1 = $types->createOne(['id' => $id, 'myName' => 'test', 'sector' => 'energy']);
		Assert::equal(1, $types->many()->count());
		Assert::type(Type::class, $object1);
		$object2 = $types->one($id);
		$object1->removeParent();
		$object2->removeParent();
		
		Assert::equal($object1, $object2);
		
		
		// 2. using generated pk
		$types->many()->delete();
		$object1 = $types->createOne(['myName' => 'test', 'sector' => 'energy']);
		Assert::equal(1, $types->many()->count());
		$object2 = $types->one($object1->getPK());
		$object1->removeParent();
		$object2->removeParent();
		Assert::equal($object1, $object2);
		
		// 3. invalid property name
		Assert::exception(static function () use ($types): void {
			$types->createOne(['myName' => 'test', 'sector' => 'energy', 'foo' => 'x']);
		}, NotExistsException::class);
		
		
		// 4. filter invalid property
		$types->many()->delete();
		$object1 = $types->createOne(['myName' => 'test', 'sector' => 'energy', 'foo' => 'x'], true);
		Assert::equal(1, $types->many()->count());
		$object2 = $types->one($object1->getPK());
		$object1->removeParent();
		$object2->removeParent();
		Assert::equal($object1, $object2);
		
		
		// 5. do not filter property name
		$types->many()->delete();
		$object1 = $types->createOne(['myName' => 'test', 'sector' => 'energy'], false);
		Assert::equal(1, $types->many()->count());
		$object2 = $types->one($object1->getPK());
		$object1->removeParent();
		$object2->removeParent();
		Assert::equal($object1, $object2);
	}
	
	/**
	 * @param int $number
	 * @param bool $generatePrimaryKey
	 * @return string[]
	 */
	private function generateMockData(int $number, bool $generatePrimaryKey = true): array
	{
		$data = [];
		
		for ($i = 0; $i !== $number; $i++) {
			$data[$i] = [];
			
			if ($generatePrimaryKey) {
				$data[$i]['id'] = "id-$i";
			}
			
			$data[$i] += ['myName' => "name$i", 'sector' => "energy"];
		}
		
		return $data;
	}
	
	/**
	 * Create Many
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testCreateMany(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		
		// 1. standart creating and maping property to column
		$types->many()->delete();
		$data = $this->generateMockData(3);
		$collection = $types->createMany($data);
		
		Assert::same(3, $collection->getAffectedNumber());
		Assert::same(['id-0', 'id-1', 'id-2'], $collection->getPossibleValues('id'));
		Assert::false($collection->isLoaded());
		Assert::same(3, $collection->count());
		
		Assert::same($collection['id-0']->toArray(), $data[0]);
		
		// 2. using generated pk
		$types->many()->delete();
		$data = $this->generateMockData(3, false);
		$collection = $types->createMany($data);
		
		Assert::same(3, $collection->getAffectedNumber());
		Assert::count(3, $collection->getPossibleValues('id'));
		Assert::false($collection->isLoaded());
		Assert::same(3, $collection->count());
		
		Assert::same($collection->first()->toArray(), ['id' => $collection->getPossibleValues('id')[0]] + $data[0]);
		
		// 3. invalid property name
		Assert::exception(static function () use ($types): void {
			$types->createMany([['myName' => 'test', 'sector' => 'energy', 'foo' => 'x']]);
		}, NotExistsException::class);
		
		// 4. filter invalid property
		$types->many()->delete();
		$data = [['myName' => 'test', 'sector' => 'energy', 'foo' => 'x']];
		$collection = $types->createMany($data, true);
		Assert::equal(1, $types->many()->count());
		Assert::same($data[0]['myName'], $collection->firstValue('myName'));
		
		
		// 5. do not filter property name
		$types->many()->delete();
		$data = [['myName' => 'test', 'fk_sector' => 'energy']];
		$collection = $types->createMany($data, null);
		Assert::equal(1, $types->many()->count());
		Assert::same($data[0]['myName'], $collection->firstValue('myName'));
		
		// 6. ignore
		$types->many()->delete();
		$data = $this->generateMockData(3);
		$data[2]['id'] = $data[1]['id'];
		$collection = $types->createMany($data, false, true);
		Assert::equal(2, $types->many()->count());
		Assert::same(2, $collection->getAffectedNumber());
		Assert::same(['id-0', 'id-1', 'id-1'], $collection->getPossibleValues('id'));
		Assert::false($collection->isLoaded());
		Assert::same(2, $collection->count());
		Assert::same($data[0], $collection['id-0']->toArray());
	}
	
	/**
	 * Sync One
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testSyncOne(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		$id = 'test-create-one';
		
		
		// // 1. sync all
		$types->many()->delete();
		$types->createOne(['id' => $id, 'myName' => 'test', 'sector' => 'energy']);
		$object1 = $types->syncOne(['id' => $id, 'myName' => 'test2', 'sector' => 'finance']);
		Assert::equal(1, $types->many()->count());
		Assert::type(Type::class, $object1);
		$object2 = $types->one($id);
		Assert::same('test2', $object2->myName);
		Assert::same('finance', $object2->sector->uuid);
		Assert::same('finance', $object1->sector->uuid);
		//$object1->removeParent();
		//$object2->removeParent();
		
		Assert::equal($object1->toArray(), $object2->toArray());
		
		// 2, sync selected
		$types->many()->delete();
		$types->createOne(['id' => $id,'myName' => 'test', 'sector' => 'energy']);
		$object1 = $types->syncOne(['id' => $id, 'myName' => 'test2', 'sector' => 'finance'], ['myName']);
		Assert::equal(1, $types->many()->count());
		Assert::type(Type::class, $object1);
		$object2 = $types->one($id);
		Assert::notEqual($object1, $object2);
		Assert::same('test2', $object2->myName);
		Assert::same('energy', $object2->sector->uuid);
	}
	
	/**
	 * Sync Many
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testSyncMany(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$types = $storm->getRepository(Type::class);
		
		// 1. sync all
		$types->many()->delete();
		$data = $this->generateMockData(3);
		$types->createMany($data);
		$data[2]['myName'] = 'testx';
		$data[2]['sector'] = 'finance';
		$collection  = $types->syncMany($data);
		Assert::same(['id-0', 'id-1', 'id-2'], $collection->getPossibleValues('id'));
		Assert::false($collection->isLoaded());
		Assert::same(3, $collection->count());
		Assert::same($collection['id-0']->toArray(), $data[0]);
		Assert::notSame($collection['id-0']->toArray(), $data[2]);
		Assert::same('testx', $types->one('id-2')->myName);
		Assert::same('finance', $types->one('id-2')->sector->uuid);
		
		
		// 2, sync selected
		$types->many()->delete();
		$data = $this->generateMockData(3);
		$types->createMany($data);
		$data[2]['myName'] = 'testx';
		$data[2]['sector'] = 'energyx';
		$collection  = $types->syncMany($data, ['myName']);
		Assert::same(['id-0', 'id-1', 'id-2'], $collection->getPossibleValues('id'));
		Assert::false($collection->isLoaded());
		Assert::same(3, $collection->count());
		Assert::same($collection['id-0']->toArray(), $data[0]);
		Assert::notSame($collection['id-0']->toArray(), $data[2]);
		Assert::same('testx', $types->one('id-2')->myName);
		Assert::same('energy', $types->one('id-2')->sector->uuid, 'energy');
	}
}

(new RepositoryTest())->run();
