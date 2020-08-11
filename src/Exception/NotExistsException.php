<?php

declare(strict_types=1);

namespace StORM\Exception;

use StORM\Helpers;
use StORM\IDumper;

class NotExistsException extends \RuntimeException implements IContextException
{
	public const MUTATION = 0;
	public const RELATION = 1;
	public const PROPERTY = 2;
	public const VALUE = 3;
	
	/**
	 * @var \StORM\ICollection|\StORM\Entity|null
	 */
	private $context;
	
	/**
	 * NotExistsException constructor.
	 * @param \StORM\ICollection|\StORM\Entity|null $context
	 * @param int $errorCode
	 * @param string $value
	 * @param string|null $source
	 * @param string[]|null $possibleValues
	 */
	public function __construct($context, int $errorCode, string $value, ?string $source = null, ?array $possibleValues = null)
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
