<?php

declare(strict_types=1);

namespace StORM\Meta;

class RelationNxN extends Relation
{
	public const TABLE_NAME_GLUE = '_nxn_';
	
	private const ANNOTATION = 'relationnxn';
	
	/**
	 * Table which is used to join
	 * @var string
	 */
	protected $via;
	
	/**
	 * @var string
	 */
	protected $sourceViaKey;
	
	/**
	 * @var string|null
	 */
	protected $sourceKeyType;
	
	/**
	 * @var string
	 */
	protected $targetViaKey;
	
	/**
	 * @var string|null
	 */
	protected $targetKeyType;

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
	 * @return void
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
	
	public function validate(): void
	{
		$this->checkRequired(['sourceViaKey', 'targetViaKey', 'via']);
		parent::validate();
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
