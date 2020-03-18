<?php

namespace StORM\Exception;

class NotExistsException extends \RuntimeException
{
	public const MUTATION = 0;
	public const RELATION = 1;
	public const PROPERTY = 3;
	public const CLASS_NAME = 2;
	public const PROPERTIES = 4;
	public const FILTER = 5;
	
	public function __construct(int $propertyCode, string $value, ?string $source = null)
	{
		if ($propertyCode === self::MUTATION) {
			$message = "Mutation $value is not set. Call storm->setAvailableMutations()";
		} elseif ($propertyCode === self::RELATION) {
			$message = "Relation $value is not defined in $source. Define @relation or fix typo";
		} elseif ($propertyCode === self::CLASS_NAME) {
			$message = "Class $value is not defined. Create class $value, fix PSR-4 autoload or fix typo";
		} elseif ($propertyCode === self::PROPERTY) {
			$message = "Property or relation $value not found";
		} elseif ($propertyCode === self::PROPERTIES) {
			$message = "Property/ies $value not found";
		}  elseif ($propertyCode === self::FILTER) {
			$message = "Filter $value not found";
		} else {
			$message = "$propertyCode of $value is not set";
		}
		
		parent::__construct($message, $propertyCode);
	}
}
