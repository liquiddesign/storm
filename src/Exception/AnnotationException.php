<?php

declare(strict_types=1);

namespace StORM\Exception;

class AnnotationException extends \RuntimeException
{
	public const JSON_PARSE = 1;
	public const STANDALONE_CONSTRAINT = 2;
	public const NO_KEY_HOLDER_CONSTRAINT = 3;
	public const ATTRIBUTE_IS_EMPTY = 4;
	public const ATTRIBUTE_NOT_EXISTS = 5;
	public const MULTIPLE_ANNOTATION = 6;
	public const ATTRIBUTE_VALUE_NOT_ALLOWED = 7;
	
	public function __construct(int $propertyCode, string $value, ?string $source = null, ?string $extra = null)
	{
		if ($propertyCode === self::JSON_PARSE) {
			$message = "JSON at $value is not valid. JSON: '$source'";
		} elseif ($propertyCode === self::STANDALONE_CONSTRAINT) {
			$message = "No relation defined in constraint $value. Define @relation with @constraint on same property";
		} elseif ($propertyCode === self::NO_KEY_HOLDER_CONSTRAINT) {
			$message = "Only keyholder of relation can define constraint $value. Do no use '[]' in @var Model[]";
		} elseif ($propertyCode === self::ATTRIBUTE_IS_EMPTY) {
			$message = "Attribute '$source'' of '$value' is empty. Populate attribute in JSON.";
		} elseif ($propertyCode === self::ATTRIBUTE_NOT_EXISTS) {
			$message = "Attribute '$source'' of '$value' not exists. Fix typo or fill proper attributes.";
		} elseif ($propertyCode === self::ATTRIBUTE_VALUE_NOT_ALLOWED) {
			$message = "Attribute '$source'' of '$value' has not allowed value. Only supported: '$extra'.";
		} elseif ($propertyCode === self::MULTIPLE_ANNOTATION) {
			$message = "Multiple annotation '@$source'' of '$value' exists. Max allowed 1 annotation of this type.";
		} else {
			$message = "$propertyCode of $value is not set";
		}
		
		parent::__construct($message, $propertyCode);
	}
}
