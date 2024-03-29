<?php

declare(strict_types = 1);

namespace StORM\Exception;

use StORM\IDumper;

class AlreadyExistsException extends \DomainException implements IContextException
{
	public const BIND_VAR = 0;
	public const ALIAS = 1;
	
	private ?IDumper $context;

	public function __construct(?IDumper $context, int $errorCode, string $value)
	{
		if ($errorCode === self::BIND_VAR) {
			$message = "Binded variable $value is already defined in collection.";
		} elseif ($errorCode === self::ALIAS) {
			$message = "Alias $value is already defined in collectio";
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
