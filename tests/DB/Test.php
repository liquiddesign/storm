<?php

namespace DB;

/**
 * @table
 * @index{"name":"test2keysUnique","columns":["testUnique"],"unique":true}
 * @index{"name":"test2keys","columns":["testNullable","testLength"],"unique":false}
 */
class Test extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @pk
	 * @var string
	 */
	public $uuid;
	
	/**
	 * @column
	 * @var string
	 */
	public $testString;
	
	/**
	 * @column
	 * @var int
	 */
	public $testInt;
	
	/**
	 * @column
	 * @var bool
	 */
	public $testBool;
	
	/**
	 * @column
	 * @var float
	 */
	public $testDouble;
	
	/**
	 * @column{"type":"text"}
	 * @var string
	 */
	public $testText;
	
	/**
	 * @column{"nullable":true}
	 * @var string
	 */
	public $testNullable;
	
	/**
	 * @column{"length":32}
	 * @var string
	 */
	public $testLength;
	
	/**
	 * @column{"mutations":true}
	 * @var string
	 */
	public $testLocale;
	
	
	/**
	 * @column{"default":"test2"}
	 * @var string
	 */
	public $testDefault;
	
	/**
	 * @column{"autoincrement":true}
	 * @var int
	 */
	public $testAutoincrement;
	
	/**
	 * @column{"unique":true}
	 * @var string
	 */
	public $testUnique;
	
	/**
	 * @relation
	 * @var \DB\Test2
	 */
	public $test;

	/**
	 * @relation{"sourceKey":"fk_account"}
	 * @var \DB\Test2
	 */
	public $test2;
	
	/**
	 * @relation
	 * @var \DB\Test2[]|\StORM\Collection
	 */
	public $tests;
	
	/**
	 * @relation{"targetKey":"fk_test"}
	 * @var \DB\Test2[]|\StORM\Collection
	 */
	public $tests2;
	
	/**
	 * Definition of N:1 or 1:1 relation
	 * @relationNxN
	 * @var \DB\Test2[]|\StORM\Collection
	 */
	public $tests3;
	
	/**
	 * Definition of N:1 or 1:1 relation
	 * @relationNxN{"sourceViaKey":"fk_test","targetViaKey":"fk_test2","via":"nxn_test_test"}
	 * @var \DB\Test2[]|\StORM\Collection
	 */
	public $tests4;
	
	/**
	 * @relation{"sourceKey":"fk_test","target":"DB\\Test3"}
	 * @constraint
	 * @var \DB\Test2
	 */
	public $testForce;
	
	/**
	 * @relation
	 * @constraint
	 * @var \DB\Test2
	 */
	public $accountMultiple;
	
	/**
	 * @relation
	 * @constraint
	 * @var \DB\Test2|null
	 */
	public $accountMultipleNullable;
	
	/**
	 * @relation{"sourceKey":"fk_test"}
	 * @constraint{"onDelete":"SET NULL"}
	 * @var \DB\Test2
	 */
	public $accountMultiple2;
	
	/**
	 * @relation{"sourceKey":"fk_test"}
	 * @constraint{"source":"tests","target":"tests2","targetKey":"uuid","sourceKey":"fk_test","onDelete":"NO ACTION","onUpdate":"NO ACTION"}
	 * @var \DB\Test2
	 */
	public $accountMultiple3;
}
