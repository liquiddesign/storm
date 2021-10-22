<?php

declare(strict_types=1);

namespace StORM;

interface IDumper
{
	public function dump(bool $return = false): ?string;
}
