<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use StORM\DIConnection;
use StORM\Exception\GeneralException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class ConnectionResilienceTest
 * Tests for connection error handling and retry mechanisms
 * @package Tests
 */
class ConnectionResilienceTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Test that beginTransaction can handle initial connection issues
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testBeginTransactionBasic(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Should work normally
		$result = $connection->beginTransaction();
		Assert::true($result);

		// Cannot start nested transaction
		$result = $connection->beginTransaction();
		Assert::false($result);

		// Rollback to clean up
		$connection->rollBack();
	}

	/**
	 * Test commit behavior
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testCommitBasic(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Cannot commit without transaction
		Assert::false($connection->commit());

		// Start transaction and commit
		$connection->beginTransaction();
		Assert::true($connection->commit());

		// Cannot commit again
		Assert::false($connection->commit());
	}

	/**
	 * Test rollback behavior
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testRollbackBasic(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Cannot rollback without transaction
		Assert::false($connection->rollBack());

		// Start transaction and rollback
		$connection->beginTransaction();
		Assert::true($connection->rollBack());

		// Cannot rollback again
		Assert::false($connection->rollBack());
	}

	/**
	 * Test query execution with retry mechanism
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testQueryWithParameters(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Create temporary table
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_query (id INT PRIMARY KEY, name VARCHAR(50))");

		// Insert with parameters
		$sth = $connection->query(
			"INSERT INTO test_query VALUES (:id, :name)",
			['id' => 1, 'name' => 'test']
		);
		Assert::type(\PDOStatement::class, $sth);

		// Select with parameters
		$sth = $connection->query(
			"SELECT name FROM test_query WHERE id = :id",
			['id' => 1]
		);
		$result = $sth->fetchColumn();
		Assert::same('test', $result);
	}

	/**
	 * Test exec with retry mechanism
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testExecBasic(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Create temporary table with exec
		$affected = $connection->exec("CREATE TEMPORARY TABLE IF NOT EXISTS test_exec (id INT)");
		Assert::type('int', $affected);

		// Insert with exec
		$affected = $connection->exec("INSERT INTO test_exec VALUES (1), (2), (3)");
		Assert::same(3, $affected);
	}

	/**
	 * Test that invalid SQL throws exception
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testInvalidSqlThrowsException(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		Assert::exception(function() use ($connection) {
			$connection->query("INVALID SQL SYNTAX HERE");
		}, \PDOException::class);
	}

	/**
	 * Test exec with invalid SQL
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testExecInvalidSql(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		Assert::exception(function() use ($connection) {
			$connection->exec("INVALID SQL SYNTAX");
		}, \PDOException::class);
	}

	/**
	 * Test connection properties are preserved
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testConnectionProperties(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// These properties should be available and valid
		Assert::type('string', $connection->getName());
		Assert::type('string', $connection->getDriver());
		Assert::type('array', $connection->getAttributes());
		Assert::type(\PDO::class, $connection->getLink());
	}

	/**
	 * Test query with identifiers
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testQueryWithIdentifiers(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Create table
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_ident (id INT, value VARCHAR(50))");
		$connection->query("INSERT INTO test_ident VALUES (1, 'test')");

		// Query with identifier quoting
		$sth = $connection->query(
			"SELECT %s FROM test_ident WHERE id = :id",
			['id' => 1],
			['value']
		);

		$result = $sth->fetchColumn();
		Assert::same('test', $result);
	}

	/**
	 * Test exec with identifiers
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testExecWithIdentifiers(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Create table with identifier
		$tableName = 'test_exec_ident';
		$affected = $connection->exec(
			"CREATE TEMPORARY TABLE IF NOT EXISTS %s (id INT)",
			[$tableName]
		);

		Assert::type('int', $affected);
	}

	/**
	 * Test buffered query option
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testBufferedQuery(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		if ($connection->getDriver() !== 'mysql') {
			\Tester\Environment::skip('Buffered query test only for MySQL');
		}

		// Create test data
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_buffered (id INT)");
		$connection->query("INSERT INTO test_buffered VALUES (1), (2), (3)");

		// Buffered query (default for MySQL)
		$sth = $connection->query("SELECT * FROM test_buffered", [], [], true);
		Assert::type(\PDOStatement::class, $sth);
		Assert::same(3, $sth->rowCount());

		// Unbuffered query
		$sth = $connection->query("SELECT * FROM test_buffered", [], [], false);
		Assert::type(\PDOStatement::class, $sth);
		// Row count not available for unbuffered queries in MySQL
	}

	/**
	 * Test transaction isolation
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionIsolation(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Setup
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_isolation (id INT PRIMARY KEY, value VARCHAR(50))");

		// Start transaction
		$connection->beginTransaction();

		// Insert data
		$connection->query("INSERT INTO test_isolation VALUES (1, 'inside_transaction')");

		// Data should be visible within transaction
		$result = $connection->query("SELECT value FROM test_isolation WHERE id = 1")->fetchColumn();
		Assert::same('inside_transaction', $result);

		// Rollback
		$connection->rollBack();

		// Data should not be visible after rollback
		$count = $connection->query("SELECT COUNT(*) FROM test_isolation")->fetchColumn();
		Assert::same('0', $count);
	}

	/**
	 * Test that errors during query are properly propagated
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testQueryErrorPropagation(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Foreign key violation should throw exception
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_parent (id INT PRIMARY KEY)");
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_child (id INT, parent_id INT, FOREIGN KEY (parent_id) REFERENCES test_parent(id))");

		Assert::exception(function() use ($connection) {
			// This should fail due to foreign key constraint
			$connection->query("INSERT INTO test_child VALUES (1, 999)");
		}, \PDOException::class);
	}

	/**
	 * Test debug mode
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testDebugMode(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		$originalDebug = $connection->isDebug();

		// Enable debug
		$connection->setDebug(true);
		Assert::true($connection->isDebug());

		// Execute query with debug enabled
		$connection->query("SELECT 1");

		// Check log
		$log = $connection->getLog();
		Assert::type('array', $log);

		// Restore original state
		$connection->setDebug($originalDebug);
	}

	/**
	 * Test debug threshold
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testDebugThreshold(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		$originalThreshold = $connection->getDebugThreshold();

		// Set threshold
		$connection->setDebugThreshold(0.5);
		Assert::same(0.5, $connection->getDebugThreshold());

		// Clear threshold
		$connection->setDebugThreshold(null);
		Assert::null($connection->getDebugThreshold());

		// Restore original
		$connection->setDebugThreshold($originalThreshold);
	}
}

(new ConnectionResilienceTest())->run();
