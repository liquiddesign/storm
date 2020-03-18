<?php

namespace DB;

/**
 * @table{"name":"stocks_industry"}
 */
class Industry extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @var string
	 */
	public $name;
	
	/**
	 * @relation
	 * @var \DB\Type
	 */
	public $type;
}
