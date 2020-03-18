<?php

namespace DB;

/**
 * @table{"name":"stocks_alert"}
 */
class Alert extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @column
	 * @var string
	 */
	public $name;
}
