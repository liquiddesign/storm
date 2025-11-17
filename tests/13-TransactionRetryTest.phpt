<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\DIConnection;
use StORM\Exception\GeneralException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class TransactionRetryTest
 * Tests for transaction retry functionality and connection resilience
 * @package Tests
 */
class TransactionRetryTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Test successful transactional execution
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalSuccess(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Simple successful transaction
		$result = $connection->transactional(function($conn) {
			$conn->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_trans (id INT, value VARCHAR(50))");
			$conn->query("INSERT INTO test_trans VALUES (1, 'test')");
			return 'success';
		});

		Assert::same('success', $result);
	}

	/**
	 * Test transactional with data persistence
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalDataPersistence(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Setup: Create temporary table
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_persist (id INT PRIMARY KEY, value VARCHAR(50))");

		// Execute transaction
		$insertedId = $connection->transactional(function($conn) {
			$conn->query("INSERT INTO test_persist VALUES (1, 'persisted')");
			return 1;
		});

		Assert::same(1, $insertedId);

		// Verify data was persisted
		$result = $connection->query("SELECT value FROM test_persist WHERE id = 1")->fetchColumn();
		Assert::same('persisted', $result);
	}

	/**
	 * Test transactional rollback on exception
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalRollback(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Setup
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_rollback (id INT PRIMARY KEY, value VARCHAR(50))");

		// Execute failing transaction
		Assert::exception(function() use ($connection) {
			$connection->transactional(function($conn) {
				$conn->query("INSERT INTO test_rollback VALUES (1, 'should_rollback')");
				throw new \Exception('Intentional failure');
			});
		}, \Exception::class, 'Intentional failure');

		// Verify data was NOT persisted (rolled back)
		$count = $connection->query("SELECT COUNT(*) FROM test_rollback")->fetchColumn();
		Assert::same('0', $count);
	}

	/**
	 * Test transactional with custom retry count
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalCustomRetryCount(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		$attemptCount = 0;

		// This should fail immediately with 1 retry (no actual retry)
		Assert::exception(function() use ($connection, &$attemptCount) {
			$connection->transactional(function($conn) use (&$attemptCount) {
				$attemptCount++;
				throw new \Exception('Non-retryable error');
			}, 1);
		}, \Exception::class, 'Non-retryable error');

		// Should have been called only once (no retry for non-retryable errors)
		Assert::same(1, $attemptCount);
	}

	/**
	 * Test transactional with invalid retry count
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalInvalidRetryCount(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		Assert::exception(function() use ($connection) {
			$connection->transactional(function($conn) {
				return 'test';
			}, 0); // Invalid: must be at least 1
		}, \InvalidArgumentException::class);
	}

	/**
	 * Test that nested transactions are handled correctly
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalNested(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Setup
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_nested (id INT PRIMARY KEY, value VARCHAR(50))");

		// Execute nested transaction (should fail because PDO doesn't support nested transactions)
		$result = $connection->transactional(function($conn) {
			$conn->query("INSERT INTO test_nested VALUES (1, 'outer')");

			// Inner transaction should return false (already in transaction)
			$innerResult = $conn->beginTransaction();
			Assert::false($innerResult);

			return 'completed';
		});

		Assert::same('completed', $result);

		// Verify outer transaction was committed
		$value = $connection->query("SELECT value FROM test_nested WHERE id = 1")->fetchColumn();
		Assert::same('outer', $value);
	}

	/**
	 * Test commit error handling
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testCommitWithoutTransaction(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Commit without transaction should return false
		$result = $connection->commit();
		Assert::false($result);
	}

	/**
	 * Test rollback error handling
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testRollbackWithoutTransaction(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Rollback without transaction should return false
		$result = $connection->rollBack();
		Assert::false($result);
	}

	/**
	 * Test transaction state management
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionStateManagement(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Verify not in transaction initially
		Assert::false($connection->getLink()->inTransaction());

		// Start transaction
		$connection->beginTransaction();
		Assert::true($connection->getLink()->inTransaction());

		// Commit
		$connection->commit();
		Assert::false($connection->getLink()->inTransaction());

		// Start another transaction
		$connection->beginTransaction();
		Assert::true($connection->getLink()->inTransaction());

		// Rollback
		$connection->rollBack();
		Assert::false($connection->getLink()->inTransaction());
	}

	/**
	 * Test that transactional properly cleans up on error
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalCleanupOnError(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Execute failing transaction
		Assert::exception(function() use ($connection) {
			$connection->transactional(function($conn) {
				throw new \RuntimeException('Test error');
			});
		}, \RuntimeException::class, 'Test error');

		// Verify connection is not in transaction after error
		Assert::false($connection->getLink()->inTransaction());
	}

	/**
	 * Test multiple sequential transactions
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testMultipleSequentialTransactions(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Setup
		$connection->query("CREATE TEMPORARY TABLE IF NOT EXISTS test_multi (id INT PRIMARY KEY, value VARCHAR(50))");

		// First transaction
		$connection->transactional(function($conn) {
			$conn->query("INSERT INTO test_multi VALUES (1, 'first')");
		});

		// Second transaction
		$connection->transactional(function($conn) {
			$conn->query("INSERT INTO test_multi VALUES (2, 'second')");
		});

		// Third transaction
		$connection->transactional(function($conn) {
			$conn->query("INSERT INTO test_multi VALUES (3, 'third')");
		});

		// Verify all were committed
		$count = $connection->query("SELECT COUNT(*) FROM test_multi")->fetchColumn();
		Assert::same('3', $count);
	}

	/**
	 * Test transactional return value preservation
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testTransactionalReturnValues(Container $container): void
	{
		$connection = $container->getByType(DIConnection::class);

		// Test various return types
		$stringResult = $connection->transactional(fn($conn) => 'string value');
		Assert::same('string value', $stringResult);

		$intResult = $connection->transactional(fn($conn) => 42);
		Assert::same(42, $intResult);

		$arrayResult = $connection->transactional(fn($conn) => ['key' => 'value']);
		Assert::same(['key' => 'value'], $arrayResult);

		$nullResult = $connection->transactional(fn($conn) => null);
		Assert::null($nullResult);

		$boolResult = $connection->transactional(fn($conn) => true);
		Assert::true($boolResult);
	}
}

(new TransactionRetryTest())->run();
