<?php

declare(strict_types=1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

class RelationNxN extends Relation
{
	public const TABLE_NAME_GLUE = '_nxn_';
	
	/**
	 * Table which is used to join
	 */
	protected string $via;
	
	protected string $sourceViaKey;
	
	protected ?string $sourceKeyType;
	
	protected string $targetViaKey;
	
	protected ?string $targetKeyType;

	public function getVia(): string
	{
		return $this->via;
	}

	public function setVia(string $via): void
	{
		$this->via = $via;
	}

	public function getSourceViaKey(): string
	{
		return $this->sourceViaKey;
	}

	public function setSourceViaKey(string $sourceViaKey): void
	{
		$this->sourceViaKey = $sourceViaKey;
	}

	public function getTargetViaKey(): string
	{
		return $this->targetViaKey;
	}

	public function setTargetViaKey(string $targetViaKey): void
	{
		$this->targetViaKey = $targetViaKey;
	}
	
	public function getSourceKeyType(): ?string
	{
		return $this->sourceKeyType;
	}
	
	public function setSourceKeyType(?string $sourceKeyType): void
	{
		$this->sourceKeyType = $sourceKeyType;
	}
	
	public function getTargetKeyType(): ?string
	{
		return $this->targetKeyType;
	}
	
	public function setTargetKeyType(?string $targetKeyType): void
	{
		$this->targetKeyType = $targetKeyType;
	}
	
	/**
	 * @param mixed[] $json
	 */
	public function loadFromArray(array $json): void
	{
		if (isset($json['keys'])) {
			$this->sourceViaKey = (string) $json['keys'][0] ?? '';
			$this->targetViaKey = (string) $json['keys'][1] ?? '';
		}
		
		parent::loadFromArray($json);
		
		return;
	}
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string(null),
			'source' => Expect::string(null),
			'target' => Expect::string(null),
			'sourceKey' => Expect::string(null),
			'targetKey' => Expect::string(null),
			'via' => Expect::string(null),
			'sourceViaKey' => Expect::string(null),
			'targetViaKey' => Expect::string(null),
			'sourceKeyType' => Expect::string(null),
			'targetViaKeyType' => Expect::string(null),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'relationnxn';
	}
}
