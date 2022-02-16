<?php

declare(strict_types = 1);

namespace StORM\Exception;

use StORM\IDumper;

interface IContextException
{
	public function getContext(): ?IDumper;
}
