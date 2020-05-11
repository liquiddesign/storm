<?php

declare(strict_types=1);

namespace StORM\Exception;

use Throwable;

class NotFoundException extends \RuntimeException
{
	/**
	 * NotFoundException constructor.
	 * @param string[] $conditions
	 * @param string|string[] $source
	 * @param \Throwable|null $previous
	 */
	public function __construct(array $conditions = [], $source = null, ?Throwable $previous = null)
	{
		$printedConditions = \print_r($conditions, true);
		$printedSource = \is_array($source) ? \implode(', ', $source) : $source;
		
		$message = 'Object/row of "'.$printedSource.'" with condition: "' . \print_r($printedConditions, true) . '" not found';
		
		parent::__construct($message, 0, $previous);
	}
}
