<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use StORM\LogItem;
use Tester\Assert;
use Tracy\Debugger;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class BasicTest
 * @package Tests
 */
class BasicTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * Testing queries
	 * @dataProvider _containers.php single_connection
	 * @expectedException \Exception
	 * @param \Nette\DI\Container $container
	 */
	public function testQueries(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		
		// 1. initial settings
		Assert::equal(true, $connection->isDebug());
		Assert::same([], $connection->getLog());
		
		// 2. simple query with binding variable
		$connection->query('SELECT 1=:var', ['var' => 2]);
		Assert::equal(1, \count($connection->getLog()));
		Assert::type(LogItem::class, $connection->getLastLogItem());
		$log = $connection->getLastLogItem();
		Assert::equal('SELECT 1=:var', $log->getSql());
		Assert::equal(['var' => 2], $log->getVars());
		Assert::type('float', $log->getTotalTime());
		Assert::equal(1, $log->getAmount());
		Assert::equal(false, $log->hasError());
		
		// 3. test query error
		Assert::exception(static function () use ($connection): void {
			$connection->query('SELECT 1=X:var', ['var' => 2]);
		}, \PDOException::class);
		$log = $connection->getLastLogItem();
		Assert::equal(true, $log->hasError());
		
		// 4. test multiple queries
		$connection->query('SELECT 0=:var', ['var' => 2]);
		$connection->query('SELECT 1=:var', ['var' => 2]);
		$log = $connection->getLastLogItem();
		Assert::equal(2, $log->getAmount());
		Assert::equal(3, \count($connection->getLog()));
		
		// 5. turning off debug
		$connection->setDebug(false);
		Assert::equal(false, $connection->isDebug());
		$connection->query('SELECT 2=:var', ['var' => 2]);
		Assert::equal(3, \count($connection->getLog()));
	}
	
	/**
	 * Advanced queries
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testAdvancedQueries(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		
		// 1. test quoting queries
		$table = 'stocks_foo';
		Assert::equal('0', $connection->query('SELECT COUNT(*) FROM %s WHERE 1=:test', ['test' => 2], [$table])->fetchColumn(0));
		$q = $connection->getQuoteIdentifierChar();
		$tableQuoted = "$q$table$q";
		$expectedSql = "SELECT COUNT(*) FROM $tableQuoted WHERE 1=:test";
		Assert::equal($expectedSql, $connection->getLastLogItem()->getSql());
		
		// 2. test exec
		Assert::equal(0, $connection->exec('DELETE FROM %s', ['stocks_foo']));
	}
	
	/**
	 * Presence if tracy panel
	 * @expectedException \Exception
	 */
	public function testTracyPanel(): void
	{
		Assert::type(\StORM\Bridges\StormTracy::class, Debugger::getBar()->getPanel(\StORM\Bridges\StormTracy::class));
	}
}

(new BasicTest())->run();
