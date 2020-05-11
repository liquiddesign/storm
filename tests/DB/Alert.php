<?php

namespace DB;

/**
 * @testClass
 * @table{"name":"stocks_alert"}
 */
class 	Alert extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @testProperty
	 * @column
	 * @var string
	 */
	public $name;
}
