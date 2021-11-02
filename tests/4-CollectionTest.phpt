<?php // @codingStandardsIgnoreLine

namespace Tests;

use Nette\DI\Container;
use StORM\Connection;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class CollectionTest
 * @package Tests
 */
class CollectionTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	private const STOCK_TABLE = 'stocks_stock';
	
	/**
	 * Test to array conversion
	 * @dataProvider _containers.php single_connection
	 * @expectedException \Exception
	 * @param \Nette\DI\Container $container
	 */
	public function testToArray(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->toArray();
		Assert::count(2, $objects);
		Assert::same([0,1], \array_keys($objects));
		Assert::type(\stdClass::class, \reset($objects));
		Assert::type(\stdClass::class, \end($objects));
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->setIndex('uuid')->toArray();
		Assert::count(2, $objects);
		Assert::same(['AAPL', 'IBM'], \array_keys($objects));
		Assert::type(\stdClass::class, \reset($objects));
		Assert::type(\stdClass::class, \end($objects));
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->toArrayOf('uuid');
		Assert::count(2, $objects);
		Assert::same(['AAPL', 'IBM'], \array_values($objects));
		Assert::same('AAPL', \reset($objects));
		Assert::same('IBM', \end($objects));
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->jsonSerialize();
		Assert::count(2, $objects);
		Assert::same([0,1], \array_keys($objects));
		Assert::type('array', \reset($objects));
	}
	
	/**
	 * Format
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testFormat(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->toArrayOf('__%s__%s', ['uuid', 'name']);
		Assert::count(2, $objects);
		Assert::same([0,1], \array_keys($objects));
		Assert::same('__AAPL__Apple Inc.', \reset($objects));
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->setIndex('uuid')->toArrayOf('__%s__', [ static function ($row) {
			return \strtolower($row->uuid);
		}]);
		Assert::count(2, $objects);
		Assert::same(['AAPL', 'IBM'], \array_keys($objects));
		Assert::same('__aapl__', \reset($objects));
		Assert::same('__ibm__', \end($objects));
		
		$objects = $storm->rows($from)->setWhere('uuid', ['AAPL', 'IBM'])->setIndex('uuid')->toArrayOf('__%s.%s__', ['__iterator', 'uuid']);
		Assert::count(2, $objects);
		Assert::same(['AAPL', 'IBM'], \array_keys($objects));
		Assert::same('__1.AAPL__', \reset($objects));
		Assert::same('__2.IBM__', \end($objects));
	}
	
	/**
	 * FROM
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testFrom(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same($from, $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table", $collection->getSql());
		Assert::contains("UPDATE $table ", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table ", $collection->getSqlDelete());
		
		// 2. adding
		$table2 = 'stocks_type';
		$collection->from([$table2]);
		Assert::same([$table, $table2], $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table,$table2", $collection->getSql());
		Assert::contains("UPDATE $table,$table2", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3. adding with alias
		$collection = $storm->rows($from);
		$table2 = 'stocks_type';
		$alias = 'test';
		$collection->from([$alias => $table2]);
		Assert::same([$table, $alias => $table2], $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table,$table2 AS $alias", $collection->getSql());
		Assert::contains("UPDATE $table,$table2 AS $alias", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4. binding
		$collection = $storm->rows($from);
		$table2 = '(SELECT * FROM stocks_type WHERE 1=:var)';
		$alias = 'test';
		$vars = ['var' => 1];
		$collection->from([$alias => $table2], $vars);
		Assert::same([$table, $alias => $table2], $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table,$table2 AS $alias", $collection->getSql());
		Assert::contains("UPDATE $table,$table2 AS $alias", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table", $collection->getSqlDelete());
		Assert::same($vars, $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 5. replacing
		$table2 = 'stocks_type';
		$collection->setFrom([$table2]);
		$toUpdateReal = ["$table2.name" => 'foo'];
		Assert::same([$table2], $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table2", $collection->getSql());
		Assert::contains("UPDATE $table2", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table2", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 6. replacing with bidning
		$collection = $storm->rows($from);
		$table2 = '(SELECT * FROM stocks_type WHERE 1=:var)';
		$table3 = 'stocks_type';
		$alias = 'test';
		$toUpdateReal = ["$table3.name" => 'foo'];
		$vars = ['var' => 1];
		$vars2 = ['var2' => 2];
		$collection->setSelect(['fooAlias' => ':var2'], $vars2)->from([$alias => $table2], $vars);
		$collection->setFrom([$table3]);
		Assert::same([$table3], $collection->getModifiers()['FROM']);
		Assert::contains(" FROM $table3", $collection->getSql());
		Assert::contains("UPDATE $table3", $collection->getSqlUpdate($toUpdate));
		Assert::contains("DELETE $table3", $collection->getSqlDelete());
		Assert::same($vars2, $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
	}
	
	/**
	 * SELECT
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testSelect(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$defaultSelect = '*';
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same([$defaultSelect], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $defaultSelect", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 2. adding
		$column = 'name';
		$collection = $storm->rows($from)->select([$column]);
		Assert::same([$defaultSelect, $column], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $defaultSelect,$column", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3. adding with alias
		$column = 'name';
		$alias = 'test';
		$collection = $storm->rows($from)->select([$alias => $column]);
		Assert::same([$defaultSelect, $alias => $column], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $defaultSelect,$column AS $alias", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4. binding
		$column = 'name=:var';
		$alias = 'test';
		$vars = ['var' => 1];
		$collection = $storm->rows($from)->select([$alias => $column], $vars);
		Assert::same([$defaultSelect, $alias => $column], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $defaultSelect,$column AS $alias", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		Assert::same($vars, $collection->getVars());
		
		// 5. replacing
		$column = 'name';
		$collection = $storm->rows($from)->setSelect([$column]);
		Assert::same([$column], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $column", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 6. replacing with bidning
		$from = '(SELECT * FROM stocks_type WHERE 1=:var)';
		$column1 = 'name=:var1';
		$column2 = 'name=:var2';
		$vars = ['var' => 1];
		$vars1 = ['var1' => 0];
		$vars2 = ['var2' => 2];
		$collection = $storm->rows()->setFrom(['test' => $from], $vars)->select([$column1], $vars1)->setSelect([$column2], $vars2);
		Assert::same([$column2], $collection->getModifiers()['SELECT']);
		Assert::contains("SELECT $column2", $collection->getSql());
		Assert::same($vars + $vars2, $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
	}
	
	/**
	 * JOIN
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testJoin(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same([], $collection->getModifiers()['JOIN']);
		Assert::notContains("JOIN", $collection->getSql());
		Assert::notContains("JOIN", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("JOIN", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 2. adding
		$table2 = 'stocks_type';
		$condition = '1=1';
		$baseCollection = $storm->rows($from)->join([$table2], $condition);
		Assert::same([['LEFT', [$table2], $condition]], $baseCollection->getModifiers()['JOIN']);
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSql());
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlDelete());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3. adding with alias
		$table3 = 'stocks_type';
		$alias3 = 'test';
		$condition = '1=1';
		$baseCollection = $baseCollection->join([$alias3 => $table3], $condition);
		Assert::same([['LEFT', [$table2], $condition], ['LEFT', [$alias3 => $table3], $condition]], $baseCollection->getModifiers()['JOIN']);
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSql());
		Assert::contains(" LEFT JOIN ($table3 AS $alias3) ON ($condition)", $baseCollection->getSql());
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" LEFT JOIN ($table3 AS $alias3) ON ($condition)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlDelete());
		Assert::contains(" LEFT JOIN ($table3 AS $alias3) ON ($condition)", $baseCollection->getSqlDelete());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4. binding
		$subselect = '(SELECT * FROM stocks_type WHERE 1=:var1)';
		$table2 = 'stocks_type';
		$vars1 = ['var1' => 1];
		$vars2 = ['var2' => 2];
		$condition = 'aux.name=:var2';
		$baseCollection = $storm->rows()->setFrom(['aux' => $subselect], $vars1)->join([$table2], $condition, $vars2);
		Assert::same([['LEFT', [$table2], $condition]], $baseCollection->getModifiers()['JOIN']);
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSql());
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" LEFT JOIN ($table2) ON ($condition)", $baseCollection->getSqlDelete());
		Assert::same($vars1 + $vars2, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		
		// 5. replacing with bidning
		$table = 'stocks_type';
		$condition = '1=1';
		$baseCollection = $storm->rows($from)->join([$table2], $condition);
		$baseCollection->setJoin([$table], $condition);
		Assert::same([['LEFT', [$table], $condition]], $baseCollection->getModifiers()['JOIN']);
		Assert::contains(" LEFT JOIN ($table) ON ($condition)", $baseCollection->getSql());
		Assert::contains(" LEFT JOIN ($table) ON ($condition)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" LEFT JOIN ($table) ON ($condition)", $baseCollection->getSqlDelete());
		Assert::same([], $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 6. remove all
		$baseCollection->clear();
		$baseCollection->setJoin([]);
		Assert::same([], $collection->getModifiers()['JOIN']);
		Assert::notContains("JOIN", $collection->getSql());
		Assert::notContains("JOIN", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("JOIN", $collection->getSqlDelete());
		Assert::same([], $baseCollection->getVars());
		
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
	}
	
	/**
	 * WHERE
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testWhere(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same([], $collection->getModifiers()['WHERE']);
		Assert::notContains("WHERE", $collection->getSql());
		Assert::notContains("WHERE", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("WHERE", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 2.a equals
		$collection = $storm->rows($from);
		$binderId = \spl_object_id($collection) . '0';
		$collection->setWhere('uuid', 'AAPL');
		Assert::same(["(uuid = :__var$binderId)"], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid = :__var$binderId)", $collection->getSql());
		Assert::contains(" WHERE (uuid = :__var$binderId)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid = :__var$binderId)", $collection->getSqlDelete());
		Assert::same(["__var$binderId" => 'AAPL'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 2.b not equals
		$collection = $storm->rows($from);
		$binderId = \spl_object_id($collection) . '0';
		$collection->setWhereNot('uuid', 'AAPL');
		Assert::same(["(uuid != :__var$binderId)"], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid != :__var$binderId)", $collection->getSql());
		Assert::contains(" WHERE (uuid != :__var$binderId)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid != :__var$binderId)", $collection->getSqlDelete());
		Assert::same(['__var0' => 'AAPL'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3.a is null
		$collection = $storm->rows($from);
		$collection->setWhere('uuid', [null]);
		Assert::same(['(uuid IS NULL)'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid IS NULL)", $collection->getSql());
		Assert::contains(" WHERE (uuid IS NULL)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid IS NULL)", $collection->getSqlDelete());
		Assert::same([], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3.b is not null
		$collection = $storm->rows($from);
		$collection->setWhereNot('uuid', [null]);
		Assert::same(['(uuid IS NOT NULL)'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid IS NOT NULL)", $collection->getSql());
		Assert::contains(" WHERE (uuid IS NOT NULL)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid IS NOT NULL)", $collection->getSqlDelete());
		Assert::same([], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.a in
		$collection = $storm->rows($from);
		$collection->setWhere('uuid', ['AAPL','IBM']);
		Assert::same(['(uuid IN (:__var0,:__var1))'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1))", $collection->getSql());
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1))", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1))", $collection->getSqlDelete());
		Assert::same(['__var0' => 'AAPL', '__var1' => 'IBM'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.b in include NULL
		$collection = $storm->rows($from);
		$collection->setWhere('uuid', ['AAPL','IBM', null]);
		Assert::same(['(uuid IN (:__var0,:__var1) OR uuid IS NULL)'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1) OR uuid IS NULL)", $collection->getSql());
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1) OR uuid IS NULL)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid IN (:__var0,:__var1) OR uuid IS NULL)", $collection->getSqlDelete());
		Assert::same(['__var0' => 'AAPL', '__var1' => 'IBM'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.c not in
		$collection = $storm->rows($from);
		$collection->setWhereNot('uuid', ['AAPL','IBM']);
		Assert::same(['(uuid NOT IN (:__var0,:__var1))'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1))", $collection->getSql());
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1))", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1))", $collection->getSqlDelete());
		Assert::same(['__var0' => 'AAPL', '__var1' => 'IBM'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.d not in include null
		$collection = $storm->rows($from);
		$collection->setWhereNot('uuid', ['AAPL','IBM', null]);
		Assert::same(['(uuid NOT IN (:__var0,:__var1) AND uuid IS NOT NULL)'], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1) AND uuid IS NOT NULL)", $collection->getSql());
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1) AND uuid IS NOT NULL)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE (uuid NOT IN (:__var0,:__var1) AND uuid IS NOT NULL)", $collection->getSqlDelete());
		Assert::same(['__var0' => 'AAPL', '__var1' => 'IBM'], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.a expression
		$collection = $storm->rows($from);
		$expression = "name != UPPER(name) AND name != ''";
		$collection->setWhere($expression);
		Assert::same(["($expression)"], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE ($expression)", $collection->getSql());
		Assert::contains(" WHERE ($expression)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE ($expression)", $collection->getSqlDelete());
		Assert::same([], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4.b not expression
		$collection = $storm->rows($from);
		$expression = "name != UPPER(name) AND name != ''";
		$collection->setWhereNot($expression);
		Assert::same(["!($expression)"], $collection->getModifiers()['WHERE']);
		Assert::contains(" WHERE !($expression)", $collection->getSql());
		Assert::contains(" WHERE !($expression)", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE !($expression)", $collection->getSqlDelete());
		Assert::same([], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 5. expression with binding
		$baseCollection = $storm->rows($from);
		$vars2 = ['var2' => 'AAPL'];
		$vars1 = ['var1' => 'X'];
		$expression = "name != UPPER(name) OR name = :var2";
		$baseCollection->setSelect(['test' => ':var1'], $vars1)->setWhere($expression, $vars2);
		Assert::same(["($expression)"], $baseCollection->getModifiers()['WHERE']);
		Assert::contains(" WHERE ($expression)", $baseCollection->getSql());
		Assert::contains(" WHERE ($expression)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE ($expression)", $baseCollection->getSqlDelete());
		Assert::same($vars1 + $vars2, $baseCollection->getVars());
		$testCollection = clone $baseCollection;
		$testCollection->clear()->setWhere('1=0')->load();
		$testCollection->clear()->setWhere('1=0')->delete();
		$testCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 6. adding
		$vars3 = ['var3' => 'IBM'];
		$expression2 = 'name = :var3';
		$baseCollection->where($expression2, $vars3);
		Assert::same(["($expression)","($expression2)"], $baseCollection->getModifiers()['WHERE']);
		Assert::contains(" WHERE ($expression) AND ($expression2)", $baseCollection->getSql());
		Assert::contains(" WHERE ($expression) AND ($expression2)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE ($expression) AND ($expression2)", $baseCollection->getSqlDelete());
		Assert::same($vars1 + $vars2 + $vars3, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 7. replacing
		$baseCollection->setWhere($expression2, $vars3);
		Assert::same(["($expression2)"], $baseCollection->getModifiers()['WHERE']);
		Assert::contains(" WHERE ($expression2)", $baseCollection->getSql());
		Assert::contains(" WHERE ($expression2)", $baseCollection->getSqlUpdate($toUpdate));
		Assert::contains(" WHERE ($expression2)", $baseCollection->getSqlDelete());
		Assert::same($vars1 + $vars3, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->delete();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
	}
	
	/**
	 * ORDER BY
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testOrderBy(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same([], $collection->getModifiers()['ORDER BY']);
		Assert::notContains("ORDER BY", $collection->getSql());
		Assert::notContains("ORDER BY", $collection->getSqlUpdate($toUpdate));
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		
		// 2. adding
		$orderBy = 'name';
		$collection = $storm->rows($from)->orderBy([$orderBy]);
		Assert::same([$orderBy], $collection->getModifiers()['ORDER BY']);
		Assert::contains(" ORDER BY $orderBy", $collection->getSql());
		Assert::contains(" ORDER BY $orderBy", $collection->getSqlUpdate($toUpdate));
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		
		// 2. adding with dir
		$orderBy = 'name';
		$dir = 'DESC';
		$collection = $storm->rows($from)->orderBy([$orderBy => $dir]);
		Assert::same([$orderBy => $dir], $collection->getModifiers()['ORDER BY']);
		Assert::contains(" ORDER BY $orderBy $dir", $collection->getSql());
		Assert::contains(" ORDER BY $orderBy $dir", $collection->getSqlUpdate($toUpdate));
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 4. binding
		$orderBy = 'IF(name = :var,1,0)';
		$vars = ['var' => 'AAPL'];
		$baseCollection = $storm->rows($from)->orderBy([$orderBy => $dir], $vars);
		Assert::same([$orderBy => $dir], $baseCollection->getModifiers()['ORDER BY']);
		Assert::contains(" ORDER BY $orderBy $dir", $baseCollection->getSql());
		Assert::contains(" ORDER BY $orderBy $dir", $baseCollection->getSqlUpdate($toUpdate));
		Assert::same($vars, $baseCollection->getVars());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 5. replacing
		$orderBy2 = 'IF(uuid = :var2,1,0)';
		$vars2 = ['var2' => 'AAPL'];
		$baseCollection->setOrderBy([$orderBy2], $vars2);
		Assert::same([$orderBy2], $baseCollection->getModifiers()['ORDER BY']);
		Assert::contains(" ORDER BY $orderBy2", $baseCollection->getSql());
		Assert::contains(" ORDER BY $orderBy2", $baseCollection->getSqlUpdate($toUpdate));
		Assert::same($vars2, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 6. replacing with bidning
		$vars1 = ['var1' => 'X'];
		$baseCollection->setSelect(['test' => ':var1'], $vars1)->setOrderBy([$orderBy], $vars);
		Assert::same([$orderBy], $baseCollection->getModifiers()['ORDER BY']);
		Assert::contains(" ORDER BY $orderBy", $baseCollection->getSql());
		Assert::contains(" ORDER BY $orderBy", $baseCollection->getSqlUpdate($toUpdate));
		Assert::same($vars1 + $vars, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		$baseCollection->clear()->setWhere('1=0')->update($toUpdateReal);
	}
	
	/**
	 * GROUP BY
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testGroupBy(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$group = 'uuid';
		$having = 'COUNT(*) > 1';
		$having2 = 'COUNT(*) > :count';
		$vars2 = [':count' => 5];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same([], $collection->getModifiers()['GROUP BY']);
		Assert::notContains("GROUP BY", $collection->getSql());
		Assert::notContains("GROUP BY", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("GROUP BY", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		
		// 2. adding
		$collection = $storm->rows($from)->groupBy([$group]);
		Assert::same([$group], $collection->getModifiers()['GROUP BY']);
		Assert::contains(" GROUP BY $group", $collection->getSql());
		Assert::exception(static function () use ($collection, $toUpdate): void {
			$collection->getSqlUpdate($toUpdate);
		}, \StORM\Exception\InvalidStateException::class);
		Assert::exception(static function () use ($collection): void {
			$collection->getSqlDelete();
		}, \StORM\Exception\InvalidStateException::class);
		$collection->clear()->setWhere('1=0')->load();
		
		// 2. adding with having
		$collection = $storm->rows($from)->groupBy([$group], $having);
		Assert::same([$group], $collection->getModifiers()['GROUP BY']);
		Assert::same($having, $collection->getModifiers()['HAVING']);
		Assert::contains(" GROUP BY $group HAVING $having", $collection->getSql());
		$collection->clear()->setWhere('1=0')->load();
		
		// 2. adding with binding
		$baseCollection = $storm->rows($from)->groupBy([$group], $having2, $vars2);
		Assert::same([$group], $baseCollection->getModifiers()['GROUP BY']);
		Assert::same($having2, $baseCollection->getModifiers()['HAVING']);
		Assert::contains(" GROUP BY $group HAVING $having2", $baseCollection->getSql());
		Assert::same($vars2, $baseCollection->getVars());
		$testCollection = clone $collection;
		$testCollection->clear()->setWhere('1=0')->load();
		
		// 5. replacing
		$select3 = 'IF(uuid = :var2,1,0)';
		$vars3 = ['var2' => 'AAPL'];
		$baseCollection->setSelect(['test' => $select3], $vars3)->setGroupBy([$group], $having);
		Assert::same([$group], $baseCollection->getModifiers()['GROUP BY']);
		Assert::contains(" GROUP BY $group HAVING $having", $baseCollection->getSql());
		Assert::same($vars3, $baseCollection->getVars());
		$baseCollection->clear()->setWhere('1=0')->load();
		
		// 5. fullgroup by
		$allColumns = ['uuid', 'name', 'ceo'];
		$collection = $storm->rows($from)->setSelect($allColumns + ['sum_enabled' => 'SUM(is_enabled)'])->setFullGroupBy(['sum_enabled']);
		Assert::same($allColumns, $collection->getModifiers()['GROUP BY']);
		Assert::contains(" GROUP BY uuid,name,ceo", $collection->getSql());
		Assert::same([], $collection->getVars());
		$collection->clear()->setWhere('1=0')->load();
	}
	
	/**
	 * LIMIT, OFFSET, PAGE
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testLimitOffset(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		$toUpdate = ['name' => 'foo'];
		$limit = 10;
		$offset = 20;
		$upperLimit = '18446744073709551615';
		$toUpdateReal = ["$table.name" => 'foo'];
		
		// 1. default settings
		$collection = $storm->rows($from);
		Assert::same(null, $collection->getModifiers()['LIMIT']);
		Assert::same(null, $collection->getModifiers()['OFFSET']);
		Assert::notContains("LIMIT", $collection->getSql());
		Assert::notContains("LIMIT", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("LIMIT", $collection->getSqlDelete());
		Assert::notContains("OFFSET", $collection->getSql());
		Assert::notContains("OFFSET", $collection->getSqlUpdate($toUpdate));
		Assert::notContains("OFFSET", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->delete();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 2. LIMIT
		$collection = $storm->rows($from)->setTake($limit);
		Assert::same($limit, $collection->getModifiers()['LIMIT']);
		Assert::contains(" LIMIT $limit", $collection->getSql());
		Assert::contains(" LIMIT $limit", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" LIMIT $limit", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		$collection->clear()->setWhere('1=0')->update($toUpdateReal);
		
		// 3. OFFSET
		$collection = $storm->rows($from)->setSkip($offset);
		Assert::same($offset, $collection->getModifiers()['OFFSET']);
		Assert::contains(" LIMIT $upperLimit OFFSET $offset", $collection->getSql());
		Assert::contains(" LIMIT $upperLimit OFFSET $offset", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" LIMIT $upperLimit OFFSET $offset", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		
		// 4. LIMIT AND OFFSET
		$collection = $storm->rows($from)->setTake($limit)->setSkip($offset);
		Assert::same($limit, $collection->getModifiers()['LIMIT']);
		Assert::same($offset, $collection->getModifiers()['OFFSET']);
		Assert::contains(" LIMIT $limit OFFSET $offset", $collection->getSql());
		Assert::contains(" LIMIT $limit OFFSET $offset", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" LIMIT $limit OFFSET $offset", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
		
		// 5. PAGE
		$collection = $storm->rows($from)->setTake($limit)->setPage(2, 10);
		Assert::same($limit, $collection->getModifiers()['LIMIT']);
		Assert::same($limit, $collection->getModifiers()['OFFSET']);
		Assert::contains(" LIMIT $limit OFFSET $limit", $collection->getSql());
		Assert::contains(" LIMIT $limit OFFSET $limit", $collection->getSqlUpdate($toUpdate));
		Assert::contains(" LIMIT $limit OFFSET $limit", $collection->getSqlDelete());
		$collection->clear()->setWhere('1=0')->load();
	}
	
	/**
	 * Agregation
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 */
	public function testAggregation(Container $container): void
	{
		$storm = $container->getByType(Connection::class);
		$table = self::STOCK_TABLE;
		$from = [$table];
		
		// 1. COUNT
		$collection = $storm->rows($from);
		$collection->count();
		Assert::contains(" COUNT(*) ", $storm->getLastLogItem()->getSql());
		$collection->enum('uuid');
		
		Assert::contains(" COUNT(DISTINCT uuid) ", $storm->getLastLogItem()->getSql());
		
		// 2. BUILD IN FUNC
		foreach (['SUM', 'AVG', 'MIN', 'MAX'] as $func) {
			$collection = $storm->rows($from);
			$call = \strtolower($func);
			$collection->$call('employees');
			Assert::contains(" $func(employees) ", $storm->getLastLogItem()->getSql());
		}
		
		// 3. CUSTOM FUNC
		$collection = $storm->rows($from);
		$collection->func('MAX', ['employees']);
		Assert::contains(" MAX(employees) ", $storm->getLastLogItem()->getSql());
	}
}

(new CollectionTest())->run();
