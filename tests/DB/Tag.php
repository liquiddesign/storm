<?php

namespace DB;

/**
 * @table{"name":"stocks_tag"}
 */
class Tag extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @var string
	 */
	public $name;
}
