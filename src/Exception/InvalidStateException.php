<?php

declare(strict_types=1);

namespace StORM\Exception;

class InvalidStateException extends \RuntimeException
{
	public const COLLECTION_ALREADY_LOADED = 0;
	public const INVALID_IDENTIFIER = 2;
	public const INVALID_BINDER_VAR = 5;
	public const KEY_HOLDER_NOT_ALLOWED = 6;
	public const PK_IS_NOT_SET = 7;
	public const GROUP_BY_NOT_ALLOWED = 8;
	public const INDEX_AND_STAR_WITHOUT_PREFIX = 9;
	public const ORDER_BY_NOT_ALLOWED = 10;
	public const IGNORE = 11;
	public const SYNCED = 12;
	
	public function __construct(int $propertyCode, ?string $extraMessage = null)
	{
		$message = null;
		
		if ($propertyCode === self::COLLECTION_ALREADY_LOADED) {
			$message = "Collection is already loaded. Call clear() on collection on do not call modifers and fetch after load / loops";
		} elseif ($propertyCode === self::INVALID_IDENTIFIER) {
			$message = "Invalid identifier: $extraMessage";
		} elseif ($propertyCode === self::INVALID_BINDER_VAR) {
			$message = "Cannot bind: $extraMessage";
		} elseif ($propertyCode === self::KEY_HOLDER_NOT_ALLOWED) {
			$message = 'Keyholder relation is not supported in CollectionRelation';
		} elseif ($propertyCode === self::PK_IS_NOT_SET) {
			$message = "Primary key $extraMessage is not set";
		} elseif ($propertyCode === self::GROUP_BY_NOT_ALLOWED) {
			$message = 'GROUP BY clause is not allowed in delete or update';
		} elseif ($propertyCode === self::ORDER_BY_NOT_ALLOWED) {
			$message = 'ORDER BY clause is not allowed in delete, remove by ->orderBy([])';
		} elseif ($propertyCode === self::INDEX_AND_STAR_WITHOUT_PREFIX) {
			$message = "Cannot use index '$extraMessage' with '*' without prefix'";
		} elseif ($propertyCode === self::IGNORE) {
			$message = "Cannot get autoincrement primary keys with IGNORE = true and multiple inserts";
		} elseif ($propertyCode === self::SYNCED) {
			$message = "Cannot get is synced with multiple inserts";
		}
		
		$message = $message ?: $extraMessage;
		
		parent::__construct($message, $propertyCode);
	}
}
