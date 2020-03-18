<?php

namespace StORM;

class Literal
{
	/**
	 * @var string
	 */
	private $value;
	
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
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->value;
	}
}
