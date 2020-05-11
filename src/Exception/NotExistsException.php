<?php

declare(strict_types=1);

namespace StORM\Exception;

use StORM\Helpers;

class NotExistsException extends \RuntimeException
{
	public const MUTATION = 0;
	public const RELATION = 1;
	public const PROPERTY = 3;
	public const CLASS_NAME = 2;
	public const VALUE = 4;
	public const FILTER = 5;
	public const ALIAS = 6;
	
	/**
	 * NotExistsException constructor.
	 * @param int $propertyCode
	 * @param string $value
	 * @param string|null $source
	 * @param string[]|null $possibleValues
	 */
	public function __construct(int $propertyCode, string $value, ?string $source = null, ?array $possibleValues = null)
	{
		$suggestions = '';
		$possibleList = '';
		
		if ($possibleValues !== null && $match = Helpers::getBestSimilarString($value, $possibleValues)) {
			$suggestions = " Do you mean '$match'?";
		}
		
		if ($possibleValues !== null) {
			$possibleList = ' Available: ' . \implode(', ', $possibleValues) . '.';
		}
		
		if ($propertyCode === self::MUTATION) {
			$message = "Unknown mutation '$value'.$possibleList";
		} elseif ($propertyCode === self::RELATION) {
			$message = "Unknown relation '$value'.$suggestions Define relation by @relation in $source";
		} elseif ($propertyCode === self::ALIAS) {
			$message = "Unknown alias '$value'.$suggestions Use 'this' for source table, otherwise call ->join() or define relation by @relation in $source.";
		} elseif ($propertyCode === self::CLASS_NAME) {
			$message = "Unknown class '$value'. Create class $value, fix PSR-4 autoload or typo.";
		} elseif ($propertyCode === self::PROPERTY) {
			$message = "Unknown property '$value' in '$source'.$suggestions Fix typo or bind property by @column in $source";
		} elseif ($propertyCode === self::VALUE) {
			$message = "Cannot get '$value' of '$source'.$suggestions";
		} elseif ($propertyCode === self::FILTER) {
			$message = "Unknown filter '$value' in $source.$suggestions";
		} else {
			$message = "$propertyCode of $value is not set";
		}
		
		parent::__construct($message, $propertyCode);
	}
}
