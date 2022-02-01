<?php

declare(strict_types=1);

namespace StORM\Exception;

use StORM\IDumper;
use Throwable;

class NotFoundException extends \Exception implements IContextException
{
	private ?IDumper $context;
	
	/**
	 * NotFoundException constructor.
	 * @param \StORM\IDumper|null $context
	 * @param string[] $conditions
	 * @param string|string[] $source
	 * @param \Throwable|null $previous
	 */
	public function __construct(?IDumper $context, array $conditions = [], $source = null, ?Throwable $previous = null)
	{
		$printedConditions = \print_r($conditions, true);
		$printedSource = \is_array($source) ? \implode(', ', $source) : $source;
		
		$message = 'Object/row of "' . $printedSource . '" with condition: "' . \print_r($printedConditions, true) . '" not found';
		$this->context = $context;
		
		parent::__construct($message, 0, $previous);
	}
	
	public function getContext(): ?IDumper
	{
		return $this->context;
	}
}
