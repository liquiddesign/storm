<?php

namespace DB;

/**
 * @table{"name":"tests"}
 */
class Test3 extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @pk
	 * @var string
	 */
	public $id;
}