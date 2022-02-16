<?php

declare(strict_types = 1);

namespace StORM\Exception;

class AnnotationException extends \DomainException
{
	public const JSON_PARSE = 1;
	public const STANDALONE_CONSTRAINT = 2;
	public const NO_KEY_HOLDER_CONSTRAINT = 3;
	public const MULTIPLE_ANNOTATION = 4;
	
	public function __construct(int $errorCode, string $value, ?string $source = null)
	{
		if ($errorCode === self::JSON_PARSE) {
			$message = "JSON at $value is not valid. JSON: '$source'";
		} elseif ($errorCode === self::STANDALONE_CONSTRAINT) {
			$message = "No relation defined in constraint $value. Define @relation with @constraint on same property";
		} elseif ($errorCode === self::NO_KEY_HOLDER_CONSTRAINT) {
			$message = "Only keyholder of relation can define constraint $value. Do no use '[]' in @var Model[]";
		} elseif ($errorCode === self::MULTIPLE_ANNOTATION) {
			$message = "Multiple annotation '@$source'' of '$value' exists. Max allowed 1 annotation of this type.";
		} else {
			$message = "$errorCode of $value is not set";
		}
		
		parent::__construct($message, $errorCode);
	}
}
