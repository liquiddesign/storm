<?php

namespace DB;

/**
 * @table{"name":"stocks_sector"}
 */
class Sector extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column{"mutations":true}
	 * @var string
	 */
	public $name;
	
	/**
	 * @column
	 * @var float
	 */
	public $performance;
	
	/**
	 * @column
	 * @var bool
	 */
	public $general;
	
	/**
	 * @column
	 * @var int
	 */
	public $no_stocks; // @codingStandardsIgnoreLine
}
