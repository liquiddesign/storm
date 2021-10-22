<?php

namespace DB;

/**
 * @table{"name":"stocks_type"}
 */
class Type extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column{"primaryKey":true}
	 * @var string
	 */
	public $id;
	
	/**
	 * @column{"name":"name"}
	 * @var string
	 */
	public $myName;
	
	/**
	 * @relation
	 * @var \DB\Sector
	 */
	public $sector;
}
