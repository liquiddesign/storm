<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Sector;
use DB\SectorRepository;
use DB\Stock;
use DB\StockRepository;
use Nette\DI\Container;
use StORM\Connection;
use StORM\Exception\NotExistsException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class EntityTest
 * @package Tests
 */
class EntityTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Fetching and data
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testFetchingData(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		/** @var \DB\Stock $apple */
		$apple = $stocks->one('AAPL', true);
		$appleWithAllColumns = $stocks->one('AAPL', true, ['*']);
		
		// get existing column
		Assert::same('Apple Inc.', $apple->name);
		
		// get non existing column, but setted
		Assert::exception(static function () use ($apple): void {
			$apple->volume;
		}, NotExistsException::class);
		Assert::same('34884482', $appleWithAllColumns->volume);
		
		// get not seted
		Assert::exception(static function () use ($apple): void {
			$apple->test;
		}, NotExistsException::class);
		
		// set existing column
		$apple->name = 'test';
		Assert::same('test', $apple->name);
		
		// set non existing column
		$apple->test = 'test';
		Assert::same('test', $apple->test);
		
		// using set value
		$apple->setValue('test', 'value2');
		Assert::same('value2', $apple->test);
		
		// using get value
		Assert::same('value2', $apple->getValue('test'));
		
		// get primary key
		Assert::same('AAPL', $apple->getPK());
		Assert::same('AAPL', $apple->uuid);
		$apple->uuid = 'test';
		Assert::same('test', $apple->getPK());
		Assert::same('test', $apple->uuid);
	}
	
	/**
	 * UpdateDelete
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testUpdateDelete(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		$stocks->many()->setWhere('uuid', 'TSLA_aux')->delete();
		
		// 1. update single property
		$tesla = $stocks->one('TSLA');
		$tesla->update(['isEnabled' => true]);
		Assert::truthy($tesla->isEnabled);
		$tesla->update(['isEnabled' => false]);
		Assert::falsey($tesla->isEnabled);
		$tesla = $stocks->one('TSLA');
		Assert::falsey($tesla->isEnabled);
		
		// return value
		$tesla->update(['isEnabled' => true]);
		
		// 2. update all
		$tesla = $stocks->one('TSLA');
		Assert::truthy($tesla->isEnabled);
		$tesla->isEnabled = 0;
		$tesla->updateAll();
		Assert::falsey($tesla->isEnabled);
		$tesla = $stocks->one('TSLA');
		Assert::falsey($tesla->isEnabled);
		
		// return value
		$tesla->update(['isEnabled' => true]);
		
		// 3. insert as new
		$tesla->uuid = 'TSLA_aux';
		$stocks->createOne($tesla->toArray());
		$tesla2 = $stocks->one('TSLA_aux');
		Assert::notNull($tesla2);
		
		// 4. delete inserted
		$tesla->delete();
		$tesla2 = $stocks->one('TSLA_aux');
		Assert::null($tesla2);
	}
	
	/**
	 * ConvertPopulate
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testConvertPopuplate(Container $container): void
	{
		// 1. create empty from array
		$storm = $container->getByType(Connection::class);
		$stocks = $storm->getRepository(Stock::class);
		$stocks->many()->setWhere('uuid', 'TSLA_aux')->delete();
		$tesla = $stocks->one('TSLA');
		
		$stock = new Stock($tesla->toArray(), $stocks);
		
		Assert::equal($stock->toArray(), $tesla->toArray());
		$stock->uuid = 'TSLA_aux';
		$stocks->createOne($stock->toArray());
		Assert::notNull($stocks->one('TSLA_aux'));
		
		// 2. populate from array
		$source = $stocks->one('AAPL');
		$tesla = $stocks->one('TSLA_aux');
		$tesla->loadFromArray($source->toArray(), true, true);
		Assert::equal($tesla->toArray(), $source->toArray());
		
		// 4. convert to array, include non colum public
		$array = $tesla->toArray([], true, true);
		Assert::contains('nonColumn', \array_keys($array));
	}
	
	/**
	 * Languages
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testLanguages(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$sectors = $storm->getRepository(Sector::class);
		$sectors->many()->setWhere('uuid', 'utilities_aux')->delete();
		$storm->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
	
		
		// 1. Get language
		$storm->setMutation('cz');
		$energy = $sectors->one('energy');
		Assert::same('Energie', $energy->name);
		Assert::same('Energie', $energy->getValue('name', 'cz'));
		Assert::same('Energy', $energy->getValue('name', 'en'));
		$array = $energy->toArray([], false);
		//Assert::false(\is_array($array['name']));
		Assert::true(isset($array["name" . '_cz']));
		Assert::true(isset($array["name" . '_en']));
		
		$array = $energy->toArray([], true);
		Assert::same('Energie', $array['name']['cz']);
		Assert::same('Energy', $array['name']['en']);
		Assert::false(isset($array["name" . '_cz']));
		Assert::false(isset($array["name" . '_en']));
		
		
		// 2. Switch language
		$storm->setMutation('en');
		$energy = $sectors->one('energy');
		Assert::same('Energy', $energy->name);
		Assert::same('Energie', $energy->getValue('name', 'cz'));
		Assert::same('Energy', $energy->getValue('name', 'en'));
		$array = $energy->toArray([], false);
		//Assert::false(\is_array($array['name']));
		Assert::true(isset($array["name" . '_cz']));
		Assert::true(isset($array["name" . '_en']));
		
		$array = $energy->toArray([], true);
		Assert::same('Energie', $array['name']['cz']);
		Assert::same('Energy', $array['name']['en']);
		Assert::false(isset($array["name" . '_cz']));
		Assert::false(isset($array["name" . '_en']));
		
		// 3. Limit languages
		$storm->setAvailableMutations(['cz' => '_cz']);
		$storm->setMutation('cz');
		$energy = $sectors->one('energy');
		Assert::same('Energie', $energy->name);
		Assert::same('Energie', $energy->getValue('name', 'cz'));
		Assert::exception(static function () use ($energy): void {
			Assert::same('Energy', $energy->getValue('name', 'en'));
		}, NotExistsException::class);
		
		$array = $energy->toArray([], false);
		//Assert::false(\is_array($array['name']));
		Assert::true(isset($array["name" . '_cz']));
		Assert::false(isset($array["name" . '_en']));
		
		$array = $energy->toArray([], true);
		Assert::same('Energie', $array['name']['cz']);
		Assert::false(isset($array["name" . '_cz']));
		
		// 4. Get null value in language
		$storm->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		$storm->setMutation('cz');
		$energy = $sectors->one('finance');
		Assert::null($energy->getValue('name', 'en'));
		
		// 5. Set languages
		$storm->setAvailableMutations(['cz' => '_cz', 'en' => '_en']);
		$storm->setMutation('cz');
		$energy = $sectors->one('materials');
		$energy->name = 'Materialy';
			Assert::same('Materialy', $energy->name);
		Assert::same('Materialy', $energy->getValue('name', 'cz'));
		//Assert::same('Materialy', $energy->toArray([], false)['name']);
		Assert::same('Materialy', $energy->toArray([], false)["name" . '_cz']);
		Assert::same('Materialy', $energy->toArray([], true)['name']['cz']);
		
		$energy = $sectors->one('materials');
		$energy->setValue('name', 'Materialy');
		Assert::same('Materialy', $energy->name);
		Assert::same('Materialy', $energy->getValue('name', 'cz'));
		//Assert::same('Materialy', $energy->toArray([], false)['name']);
		Assert::same('Materialy', $energy->toArray([], false)["name" . '_cz']);
		Assert::same('Materialy', $energy->toArray([], true)['name']['cz']);
		
		$energy = $sectors->one('materials');
		$energy->setValue('name', 'Materials2', 'en');
		Assert::same('Materialy', $energy->name);
		Assert::same('Materialy', $energy->getValue('name', 'cz'));
		Assert::same('Materials2', $energy->getValue('name', 'en'));
		//Assert::same('Materials', $energy->toArray([], false)['name']);
		Assert::same('Materialy', $energy->toArray([], false)["name" . '_cz']);
		Assert::same('Materials2', $energy->toArray([], false)["name" . '_en']);
		Assert::same('Materialy', $energy->toArray([], true)['name']['cz']);
		Assert::same('Materials2', $energy->toArray([], true)['name']['en']);
	
		// 6. update with locales
		$utility = $sectors->one('utilities');
		$utility->setValue('name', 'u-cz', 'cz');
		$utility->setValue('name', 'u-en', 'en');
		$utility->updateAll();
		$utility = $sectors->one('utilities');
		Assert::same('u-cz', $utility->getValue('name', 'cz'));
		Assert::same('u-en', $utility->getValue('name', 'en'));
		
		$utility = $sectors->one('utilities');
		$utility->setValue('name', 'u2-cz', 'cz');
		$utility->setValue('name', 'u2-en', 'en');
		$utility->updateAll();
		$utility = $sectors->one('utilities');
		Assert::same('u2-cz', $utility->getValue('name', 'cz'));
		Assert::same('u2-en', $utility->getValue('name', 'en'));
		
		// 7. insert with locales
		$utility = $sectors->one('utilities');
		$utility->setValue('name', 'u2-cz', 'cz');
		$utility->setValue('name', 'u2-en', 'en');
		$utility->uuid = 'utilities_aux';
		$sectors->createOne($utility->toArray());
	}
}

(new EntityTest())->run();
