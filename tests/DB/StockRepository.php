<?php

namespace DB;

use StORM\ICollection;

class StockRepository extends \StORM\Repository implements IStockRepository // @codingStandardsIgnoreLine
{
	public function filterId(string $value, ICollection $collection): void
	{
		$collection->where('this.uuid', $value);
	}
}
