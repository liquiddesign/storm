<?php

namespace StORM\Exception;

use Throwable;

class NotFoundException extends \RuntimeException
{
	/**
	 * NotFoundException constructor.
	 * @param mixed $message
	 * @param int $code
	 * @param \Throwable|null $previous
	 */
	public function __construct($message = "", $code = 0, ?Throwable $previous = null)
	{
		$message = 'Object with condition: "' . \print_r($message, true) . '" not found';
		
		parent::__construct($message, $code, $previous);
	}
}
