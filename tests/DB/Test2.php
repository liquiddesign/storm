<?php

namespace DB;

/**
 * @table{"name":"tests","collate":"utf8_czech_ci","engine":"innoDB"}
 * @trigger{"name":"test","manipulation":"DELETE","timing":"BEFORE","statement":"DELETE FROM tests WHERE old.id=new.id"}
 * @trigger{"name":"test2","manipulation":"DELETE","timing":"BEFORE","statement":"DELETE FROM tests WHERE old.id=new.id"}
 */
class Test2 extends \StORM\Entity // @codingStandardsIgnoreLine
{
	/**
	 * @pk
	 * @var string
	 */
	public $id;
}
