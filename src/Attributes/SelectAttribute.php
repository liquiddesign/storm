<?php

namespace StORM\Attributes;

#[\Attribute]
class SelectAttribute
{
	public function __construct(public string $query)
	{
	}
}
