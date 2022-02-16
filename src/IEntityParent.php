<?php

declare(strict_types = 1);

namespace StORM;

interface IEntityParent
{
	public function getRepository(): Repository;
}
