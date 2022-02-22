<?php

declare(strict_types = 1);

namespace StORM\Exception;

class AnnotationException extends \DomainException
{
	public const NOT_DEFINED_RELATION = 1;
	public const STANDALONE_CONSTRAINT = 2;
	public const NO_KEY_HOLDER_CONSTRAINT = 3;
	public const MULTIPLE_ANNOTATION = 4;
	public const INVALID_SCHEMA = 5;
	
	public function __construct(int $errorCode, string $value, ?string $source = null)
	{
		if ($errorCode === self::NOT_DEFINED_RELATION) {
			$message = "Relation $value is not defined. Define relation with type or doc comment RelationCollection<Type>";
		} elseif ($errorCode === self::STANDALONE_CONSTRAINT) {
			$message = "No relation defined in constraint $value. Define @relation with @constraint on same property";
		} elseif ($errorCode === self::NO_KEY_HOLDER_CONSTRAINT) {
			$message = "Only keyholder of relation can define constraint $value. Do no use '[]' in @var Model[]";
		} elseif ($errorCode === self::MULTIPLE_ANNOTATION) {
			$message = "Multiple annotation '@$source'' of '$value' exists. Max allowed 1 annotation of this type.";
		} elseif ($errorCode === self::INVALID_SCHEMA) {
			$message = "Annotation '@$source'' of '$value' is invalid.";
		} else {
			$message = "$errorCode of $value is not set";
		}
		
		parent::__construct($message, $errorCode);
	}
}
