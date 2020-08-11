<?php

namespace DB;

use StORM\Entity;

/**
 * Table of stocks
 * @property mixed test
 * @property mixed volume
 * @property string uuid
 * @table{"name":"stocks_stock"}
 */
class Stock extends Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @var string
	 */
	public $name;
	
	/**
	 * Stock currency CZK, TEST
	 * @column
	 * @var string
	 */
	public $currency;
	
	/**
	 * @column{"name":"is_enabled"}
	 * @var string
	 */
	public $isEnabled;
	
	/**
	 * @relation
	 * @var \DB\Sector
	 */
	public $sector;
	
	/**
	 * @relation
	 * @var \DB\Industry
	 */
	public $industry;
	
	/**
	 * @relation
	 * @var \DB\Alert[]|\StORM\IRelation
	 */
	public $alerts;
	
	/**
	 * @relationNxN
	 * @var \DB\Tag[]|\StORM\IRelation
	 */
	public $tags;
	
	/**
	 * @var string
	 */
	public $nonColumn;
}
