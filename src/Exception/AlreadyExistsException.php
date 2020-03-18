<?php

namespace StORM\Exception;

class AlreadyExistsException extends \RuntimeException
{
	public const BIND_VAR = 0;
	public const ALIAS = 1;
	
	public function __construct(int $propertyCode, string $value)
	{
		if ($propertyCode === self::BIND_VAR) {
			$message = "Binded variable $value is already defined in collection.";
		} elseif ($propertyCode === self::ALIAS) {
			$message = "Alias $value is already defined in collectio";
		} else {
			$message = "$propertyCode of $value is not set";
		}
		
		parent::__construct($message, $propertyCode);
	}
}
