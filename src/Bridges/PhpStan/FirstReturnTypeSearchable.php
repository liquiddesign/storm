<?php

declare(strict_types = 1);

namespace StORM\Bridges\PhpStan;

use StORM\ISearchableCollection;

class FirstReturnTypeSearchable extends FirstReturnType implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return ISearchableCollection::class;
	}
}
