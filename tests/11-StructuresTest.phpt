<?php // @codingStandardsIgnoreLine

namespace Tests;

use DB\Test;
use DB\Test2;
use DB\Test2Repository;
use DB\TestRepository;
use Nette\DI\Container;
use StORM\Connection;
use StORM\Meta\RelationNxN;
use StORM\Meta\Trigger;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Class StructuresTest
 * @package Tests
 */
class StructuresTest extends \Tester\TestCase // @codingStandardsIgnoreLine
{
	/**
	 * @dataProvider _containers.php single_connection
	 * @param \Nette\DI\Container $container
	 * @expectedException \Exception
	 */
	public function testColumns(Container $container): void
	{
		$connection = $container->getByType(Connection::class);
		
		$test = $connection->getRepository(Test::class);
		$test2 = $connection->getRepository(Test2::class);
		
		$meta = $test->getStructure();
		$meta2 = $test2->getStructure();
		
		Assert::type(\StORM\Meta\Structure::class, $meta);
		Assert::type(\StORM\Meta\Structure::class, $meta2);
		
		Assert::equal('stocks_test', $meta->getTable()->getName());
		Assert::equal('tests', $meta2->getTable()->getName());
		
		Assert::equal('uuid', $meta->getPK()->getName());
		Assert::equal('id', $meta2->getPK()->getName());
		
		Assert::equal(null, $meta->getTable()->getCollate());
		Assert::equal('utf8_czech_ci', $meta2->getTable()->getCollate());
		
		Assert::equal(null, $meta->getTable()->getEngine());
		Assert::equal('innoDB', $meta2->getTable()->getEngine());
		
		Assert::equal([], $meta->getTriggers());
		
		Assert::equal(true, isset($meta2->getTriggers()['test']));
		$trigger = $meta2->getTriggers()['test'];
		Assert::type(Trigger::class, $trigger);
		Assert::equal(\DB\Test2::class, $trigger->getEntityClass());
		Assert::equal('test', $trigger->getName());
		Assert::equal('DELETE', $trigger->getManipulation());
		Assert::equal('BEFORE', $trigger->getTiming());
		Assert::equal('DELETE FROM tests WHERE old.id=new.id', $trigger->getStatement());
		
		$names = \array_keys($meta->getColumns());
		$array = ['testString', 'testInt', 'testBool', 'testDouble', 'testText','testNullable','testLength', 'testLocale', 'testDefault',
			'testAutoincrement', 'testUnique', 'test', 'test2', 'testForce',
			'accountMultiple', 'accountMultipleNullable', 'accountMultiple2', 'accountMultiple3',
		];
		
		foreach ($array as $property) {
			Assert::contains($property, $names);
		}
		
		$names =  \array_keys($meta->getRelations());
		$array = ['test','tests', 'tests2', 'tests2', 'tests3', 'tests4', 'testForce', 'accountMultiple', 'accountMultiple2', 'accountMultiple3'];
		
		foreach ($array as $property) {
			Assert::contains($property, $names);
		}
		
		
		$constraints = $meta->getConstraints();
		
		Assert::equal(true, isset($constraints['stocks_test_testForce']));
		$constraint = $constraints['stocks_test_testForce'];
		Assert::equal(\DB\Test::class, $constraint->getEntityClass());
		Assert::equal('stocks_test_testForce', $constraint->getName());
		Assert::equal('stocks_test', $constraint->getSource());
		Assert::equal('tests', $constraint->getTarget());
		Assert::equal('fk_test', $constraint->getSourceKey());
		Assert::equal('id', $constraint->getTargetKey());
		Assert::equal(null, $constraint->getOnUpdate());
		Assert::equal(null, $constraint->getOnDelete());
		
		Assert::equal(true, isset($constraints['stocks_test_accountMultiple']));
		$constraint = $constraints['stocks_test_accountMultiple'];
		Assert::equal('tests', $constraint->getTarget());
		Assert::equal('fk_accountMultiple', $constraint->getSourceKey());
		
		
		Assert::equal(true, isset($constraints['stocks_test_accountMultipleNullable']));
		$constraint = $constraints['stocks_test_accountMultipleNullable'];
		Assert::equal(null, $constraint->getOnDelete());
		Assert::equal(null, $constraint->getOnUpdate());
		
		Assert::equal(true, isset($constraints['stocks_test_accountMultiple2']));
		$constraint = $constraints['stocks_test_accountMultiple2'];
		Assert::equal('SET NULL', $constraint->getOnDelete());
		Assert::equal(null, $constraint->getOnUpdate());
		
		Assert::equal(true, isset($constraints['stocks_test_accountMultiple3']));
		$constraint = $constraints['stocks_test_accountMultiple3'];
		Assert::equal(\DB\Test::class, $constraint->getEntityClass());
		Assert::equal('stocks_test_accountMultiple3', $constraint->getName());
		Assert::equal('tests', $constraint->getSource());
		Assert::equal('tests2', $constraint->getTarget());
		Assert::equal('fk_test', $constraint->getSourceKey());
		Assert::equal('uuid', $constraint->getTargetKey());
		Assert::equal('NO ACTION', $constraint->getOnUpdate());
		Assert::equal('NO ACTION', $constraint->getOnDelete());
		
		$indexes = $meta->getIndexes();
		
		Assert::equal(true, isset($indexes['stocks_test_testUnique']));
		$index = $indexes['stocks_test_testUnique'];
		Assert::equal('stocks_test_testUnique', $index->getName());
		Assert::equal(\DB\Test::class, $index->getEntityClass());
		Assert::equal(['testUnique'], $index->getColumns());
		Assert::equal(true, $index->isUnique());
		
		Assert::equal(true, isset($indexes['test2keys']));
		$index = $indexes['test2keys'];
		Assert::equal(['testNullable','testLength'], $index->getColumns());
		Assert::equal(false, $index->isUnique());
		
		Assert::equal(true, isset($indexes['stocks_test_testForce']));
		$index = $indexes['stocks_test_testForce'];
		Assert::equal(['fk_test'], $index->getColumns());
		Assert::equal(false, $index->isUnique());
		
		Assert::equal(true, isset($indexes['stocks_test_accountMultiple']));
		$index = $indexes['stocks_test_accountMultiple'];
		Assert::equal(['fk_accountMultiple'], $index->getColumns());
		Assert::equal(false, $index->isUnique());
		
		
		
		$columns = $meta->getColumns();
		Assert::equal(true, isset($columns['testString']));
		$column = $columns['testString'];
		Assert::equal('testString', $column->getName());
		Assert::equal(Test::class, $column->getEntityClass());
		Assert::equal('string', $column->getPropertyType());
		Assert::equal(null, $column->getType());
		Assert::equal(null, $column->getLength());
		Assert::equal(null, $column->getDefault());
		Assert::equal('', $column->getExtra());
		Assert::equal(false, $column->isUnique());
		Assert::equal(false, $column->isNullable());
		Assert::equal(null, $column->isAutoincrement());
		Assert::equal(false, $column->hasMutations());
		
		
		Assert::equal(true, isset($columns['testInt']));
		$column = $columns['testInt'];
		Assert::equal('int', $column->getPropertyType());
		Assert::equal(null, $column->getType());
		Assert::equal(null, $column->getLength());
		
		Assert::equal(true, isset($columns['testBool']));
		$column = $columns['testBool'];
		Assert::equal('bool', $column->getPropertyType());
		Assert::equal(null, $column->getType());
		Assert::equal(null, $column->getLength());
		
		Assert::equal(true, isset($columns['testDouble']));
		$column = $columns['testDouble'];
		Assert::equal('float', $column->getPropertyType());
		Assert::equal(null, $column->getType());
		Assert::equal(null, $column->getLength());
		
		Assert::equal(true, isset($columns['testText']));
		$column = $columns['testText'];
		Assert::equal('text', $column->getType());
		Assert::equal(null, $column->getLength());
		
		Assert::equal(true, isset($columns['testNullable']));
		$column = $columns['testNullable'];
		Assert::equal(true, $column->isNullable());
		
		Assert::equal(true, isset($columns['testLength']));
		$column = $columns['testLength'];
		Assert::equal(32, $column->getLength());
		
		Assert::equal(true, isset($columns['testLocale']));
		$column = $columns['testLocale'];
		Assert::equal(true, $column->hasMutations());
		
		Assert::equal(true, isset($columns['testUnique']));
		$column = $columns['testUnique'];
		Assert::equal(true, $column->isUnique());
		
		Assert::equal(true, isset($columns['testAutoincrement']));
		$column = $columns['testAutoincrement'];
		Assert::equal(true, $column->isAutoincrement());
		
		Assert::equal(true, isset($columns['accountMultiple']));
		$column = $columns['accountMultiple'];
		Assert::equal('fk_accountMultiple', $column->getName());
		
		Assert::equal(true, isset($columns['testDefault']));
		$column = $columns['testDefault'];
		Assert::equal('test2', $column->getDefault());
		
		$relations = $meta->getRelations();
		
		Assert::equal(true, isset($relations['testForce']));
		$relation = $relations['testForce'];
		Assert::equal(\DB\Test::class, $relation->getEntityClass());
		Assert::equal('testForce', $relation->getName());
		Assert::equal(\DB\Test::class, $relation->getSource());
		Assert::equal('DB\Test3', $relation->getTarget());
		Assert::equal('fk_test', $relation->getSourceKey());
		Assert::equal('id', $relation->getTargetKey());
		Assert::equal(true, $relation->isKeyHolder());
		
		Assert::equal(true, isset($relations['accountMultipleNullable']));
		$relation = $relations['accountMultipleNullable'];
		Assert::equal(\DB\Test::class, $relation->getEntityClass());
		Assert::equal('accountMultipleNullable', $relation->getName());
		Assert::equal(\DB\Test::class, $relation->getSource());
		Assert::equal(\DB\Test2::class, $relation->getTarget());
		Assert::equal('fk_accountMultipleNullable', $relation->getSourceKey());
		Assert::equal('id', $relation->getTargetKey());
		Assert::equal(true, $relation->isKeyHolder());
		
		
		Assert::equal(true, isset($relations['tests']));
		$relation = $relations['tests'];
		Assert::equal(\DB\Test::class, $relation->getEntityClass());
		Assert::equal('tests', $relation->getName());
		Assert::equal(\DB\Test::class, $relation->getSource());
		Assert::equal(\DB\Test2::class, $relation->getTarget());
		Assert::equal('uuid', $relation->getSourceKey());
		Assert::equal('fk_test', $relation->getTargetKey());
		Assert::equal(false, $relation->isKeyHolder());
		
		Assert::equal(true, isset($relations['tests2']));
		$relation = $relations['tests2'];
		Assert::equal('uuid', $relation->getSourceKey());
		Assert::equal('fk_test', $relation->getTargetKey());
		
		//$tests3
		Assert::equal(true, isset($relations['tests3']));
		$relation = $relations['tests3'];
		
		Assert::type(RelationNxN::class, $relation);
		Assert::equal(\DB\Test::class, $relation->getEntityClass());
		Assert::equal('tests3', $relation->getName());
		Assert::equal('fk_test', $relation->getSourceViaKey());
		Assert::equal('fk_test2', $relation->getTargetViaKey());
		Assert::equal('stocks_test_nxn_tests', $relation->getVia());
		
		Assert::equal(\DB\Test::class, $relation->getSource());
		Assert::equal(\DB\Test2::class, $relation->getTarget());
		Assert::equal('uuid', $relation->getSourceKey());
		Assert::equal('id', $relation->getTargetKey());
		
		Assert::equal(true, isset($relations['tests4']));
		$relation = $relations['tests4'];
		
		Assert::type(RelationNxN::class, $relation);
		Assert::equal(\DB\Test::class, $relation->getEntityClass());
		Assert::equal('tests4', $relation->getName());
		Assert::equal('fk_test', $relation->getSourceViaKey());
		Assert::equal('fk_test2', $relation->getTargetViaKey());
		Assert::equal('nxn_test_test', $relation->getVia());
		
		Assert::equal(\DB\Test::class, $relation->getSource());
		Assert::equal(\DB\Test2::class, $relation->getTarget());
		Assert::equal('uuid', $relation->getSourceKey());
		Assert::equal('id', $relation->getTargetKey());
	}
}

(new StructuresTest())->run();
