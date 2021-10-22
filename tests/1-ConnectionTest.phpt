<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\IStockRepository;
use DB\Stock;
use Nette\DI\Container;
use Nette\Neon\Neon;
use StORM\DIConnection;
use StORM\SchemaManager;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionTest
 * @package Tests
 */
class ConnectionTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Test connection
	 * @dataProvider _containers.php single_connection
	 * @expectedException \Exception
	 * @param \Nette\DI\Container $container
	 */
	public function testConnection(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);
		
		Assert::same('default', $connection->getName());
		Assert::type(DIConnection::class, $connection);
	}
	
	/**
	 * Test multiple connections in one code, creating and changing
	 * @dataProvider _containers.php multiple_connections
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testMultipleConnection(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);
		Assert::type(DIConnection::class, $connection);
		Assert::same('default', $connection->getName());
		
		$connection2 = $container->getService('storm.test');
		Assert::type(DIConnection::class, $connection2);
		Assert::same('test', $connection2->getName());
		
		$stocks = $container->getByType(IStockRepository::class);
		$test = $stocks->getConnection();
		Assert::same($test, $connection);
		
		$stocks->setConnection($connection2);
		$test = $stocks->getConnection();
		Assert::same($test, $connection2);
	}
	
	/**
	 * Advanced connection settings
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testAdditionalSettings(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);
		Assert::type(DIConnection::class, $connection);
		Assert::same('mysql', $connection->getDriver());
		// collate
		Assert::same('utf8_czech_ci', $connection->query("SHOW VARIABLES LIKE 'collation_connection'")->fetchColumn(1));
		// charset
		Assert::same('utf8', $connection->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetchColumn(1));
		// languages
		Assert::same(['en' => '_en'], $connection->getAvailableMutations());
		// primaryKeyGenerator
		Assert::notNull($connection->generatePrimaryKey());
	}
	
	/**
	 * Creating standalone connection
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testStandalone(Container $container): void
	{
		$cacheStorage = new \Nette\Caching\Storages\FileStorage(__DIR__ . '/temp');
		$neon = Neon::decode(\file_get_contents($container->parameters['appDir'] . '/configs/single_connection.neon'));
		$name = 'default';
		
		$driver = $neon['storm']['connections'][$name]['driver'];
		$dbName = $neon['storm']['connections'][$name]['dbname'];
		$host = $neon['storm']['connections'][$name]['host'];
		$user = $neon['storm']['connections'][$name]['user'];
		$password = $neon['storm']['connections'][$name]['password'];
		
		$connection = new DIConnection($container, $name, "$driver:dbname=$dbName;host=$host", $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$schemaManager = new SchemaManager($connection, $cacheStorage);
		$schemaManager->getStructure(Stock::class);
	}
	
	/**
	 * Connection properities
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testProperties(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);
		
		$neon = Neon::decode(\file_get_contents($container->parameters['appDir'] . '/configs/single_connection.neon'));
		$name = 'default';
		
		$driver = $neon['storm']['connections'][$name]['driver'];
		$dbName = $neon['storm']['connections'][$name]['dbname'];
		$user = $neon['storm']['connections'][$name]['user'];
		
		Assert::same($user, $connection->getUser());
		Assert::same($dbName, $connection->getDatabaseName());
		Assert::same($driver, $connection->getDriver());
		Assert::type('array', $connection->getAttributes());
		Assert::type(\PDO::class, $connection->getLink());
	}

}

(new ConnectionTest())->run();
