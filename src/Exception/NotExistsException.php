<?php

declare(strict_types=1);

namespace StORM\Exception;

use StORM\Helpers;
use StORM\IDumper;

class NotExistsException extends \DomainException implements IContextException
{
	public const MUTATION = 0;
	public const RELATION = 1;
	public const PROPERTY = 2;
	public const VALUE = 3;
	public const SERIALIZE = 4;
	public const REAL_PK = 5;
	public const SCHEMA = 6;
	
	private ?IDumper $context;
	
	/**
	 * NotExistsException constructor.
	 * @param \StORM\IDumper|null $context
	 * @param int $errorCode
	 * @param string $value
	 * @param string|null $source
	 * @param array<string>|null $possibleValues
	 */
	public function __construct(?IDumper $context, int $errorCode, string $value, ?string $source = null, ?array $possibleValues = null)
	{
		$suggestions = '';
		$possibleList = '';
		
		if ($possibleValues !== null && $match = Helpers::getBestSimilarString($value, $possibleValues)) {
			$suggestions = " Do you mean '$match'?";
		}
		
		if ($possibleValues !== null) {
			$possibleList = ' Available: ' . \implode(', ', $possibleValues) . '.';
		}
		
		if ($errorCode === self::MUTATION) {
			$message = "Unknown mutation '$value'.$possibleList";
		} elseif ($errorCode === self::RELATION) {
			$message = "Unknown relation '$value'.$suggestions Define relation by @relation in $source";
		} elseif ($errorCode === self::PROPERTY) {
			$message = "Unknown property/column '$value' in '$source'.$suggestions Fix typo or bind property by @column in $source";
		} elseif ($errorCode === self::VALUE) {
			$message = "Cannot get '$value' of '$source'.$suggestions";
		} elseif ($errorCode === self::SERIALIZE) {
			$message = "Objects was serialized call $value";
		} elseif ($errorCode === self::REAL_PK) {
			$message = "Primary key or table '$value' in schema '$source'";
		} elseif ($errorCode === self::SCHEMA) {
			$message = "Cannot parse schema of class '$value'";
		} else {
			$message = "$errorCode of $value is not set";
		}
		
		$this->context = $context;
		
		parent::__construct($message, $errorCode);
	}
	
	public function getContext(): ?IDumper
	{
		return $this->context;
	}
}
