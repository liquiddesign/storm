<?php

declare(strict_types = 1);

namespace StORM\Exception;

use StORM\IDumper;

class InvalidStateException extends \LogicException implements IContextException
{
	public const CONNECTION_NOT_SET = 0;
	public const COLLECTION_ALREADY_LOADED = 1;
	public const INVALID_IDENTIFIER = 2;
	public const INVALID_BINDER_VAR = 3;
	public const KEY_HOLDER_NOT_ALLOWED = 4;
	public const PK_IS_NOT_SET = 5;
	public const GROUP_BY_NOT_ALLOWED = 6;
	public const INDEX_AND_STAR_WITHOUT_PREFIX = 7;
	public const ORDER_BY_NOT_ALLOWED = 8;
	public const IGNORE = 9;
	public const SYNCED = 10;
	public const FULL_GROUP_BY_WITH_STAR = 11;
	
	private ?IDumper $context;
	
	public function __construct(?IDumper $context, int $errorCode, ?string $extraMessage = null)
	{
		$message = null;
		
		if ($errorCode === self::CONNECTION_NOT_SET) {
			$message = 'Connection is not set. Call setConnection()';
		} elseif ($errorCode === self::COLLECTION_ALREADY_LOADED) {
			$message = 'Collection is already loaded. Call clear() on collection on do not call modifers and fetch after load / loops';
		} elseif ($errorCode === self::INVALID_IDENTIFIER) {
			$message = "Invalid identifier: $extraMessage";
		} elseif ($errorCode === self::INVALID_BINDER_VAR) {
			$message = "Cannot bind: $extraMessage";
		} elseif ($errorCode === self::KEY_HOLDER_NOT_ALLOWED) {
			$message = 'Keyholder relation is not supported in CollectionRelation';
		} elseif ($errorCode === self::PK_IS_NOT_SET) {
			$message = "Primary key $extraMessage is not set";
		} elseif ($errorCode === self::GROUP_BY_NOT_ALLOWED) {
			$message = 'GROUP BY clause is not allowed in delete or update';
		} elseif ($errorCode === self::ORDER_BY_NOT_ALLOWED) {
			$message = 'ORDER BY clause is not allowed in delete, remove by ->orderBy([])';
		} elseif ($errorCode === self::INDEX_AND_STAR_WITHOUT_PREFIX) {
			$message = "Cannot use index '$extraMessage' with '*' without prefix'";
		} elseif ($errorCode === self::FULL_GROUP_BY_WITH_STAR) {
			$message = "Cannot use setFullGroupBy with '*' name each column in SELECT clause";
		} elseif ($errorCode === self::IGNORE) {
			$message = 'Cannot get autoincrement primary keys with IGNORE = true and multiple inserts';
		} elseif ($errorCode === self::SYNCED) {
			$message = 'Cannot get is synced with multiple inserts';
		}
		
		$message = $message ?: $extraMessage;
		$this->context = $context;
		
		parent::__construct($message, $errorCode);
	}
	
	public function getContext(): ?IDumper
	{
		return $this->context;
	}
}
