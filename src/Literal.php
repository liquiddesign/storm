<?php

declare(strict_types=1);

namespace StORM;

class Literal
{
	private string $value;
	
	/**
	 * Literal constructor.
	 * @param mixed $value
	 */
	public function __construct($value)
	{
		$this->value = (string) $value;
	}
	
	/**
	 * Convert to string
	 */
	public function __toString(): string
	{
		return $this->value;
	}
}
