<?php

declare(strict_types=1);

namespace StORM\Meta;

class Relation extends PropertyAnnotation
{
	private const ANNOTATION = 'relation';
	
	/**
	 * @var string
	 */
	protected $source;
	
	/**
	 * @var string
	 */
	protected $target;
	
	/**
	 * @var string
	 */
	protected $sourceKey;
	
	/**
	 * @var string
	 */
	protected $targetKey;
	
	/**
	 * @var string|null
	 */
	protected $keyType;
	
	/**
	 * @var bool
	 */
	protected $isKeyHolder;
	
	
	public function getSource(): string
	{
		return $this->source;
	}
	
	public function setSource(string $source): void
	{
		$this->source = $source;
	}
	
	public function getTarget(): string
	{
		return $this->target;
	}
	
	public function setTarget(string $target): void
	{
		$this->target = $target;
	}
	
	public function getSourceKey(): string
	{
		return $this->sourceKey;
	}
	
	public function setSourceKey(string $sourceKey): void
	{
		$this->sourceKey = $sourceKey;
	}
	
	public function getTargetKey(): string
	{
		return $this->targetKey;
	}
	
	public function setTargetKey(string $targetKey): void
	{
		$this->targetKey = $targetKey;
	}
	
	public function getKeyType(): ?string
	{
		return $this->keyType;
	}
	
	public function setKeyType(?string $keyType): void
	{
		$this->keyType = $keyType;
	}
	
	public function isKeyHolder(): ?bool
	{
		return $this->isKeyHolder;
	}
	
	public function setKeyHolder(bool $holder): void
	{
		$this->isKeyHolder = $holder;
	}
	
	public function loadFromType(string $type): bool
	{
		$types = \explode('|', $type);
		
		foreach ($types as $type) {
			$typeLower = \strtolower($type);
			
			if ($typeLower === 'null') {
				continue;
			}
			
			$offset = \strpos($type, '[]');
			$target = $offset === false ? $type : \substr($type, 0, $offset);
			
			if (Structure::isEntityClass($target)) {
				$this->target = \substr($target, 0, 1) === "\\" ? \substr($target, 1) : $target;
				$this->source = $this->getEntityClass();
				$this->isKeyHolder = $offset === false;
				
				return true;
			}
		}
		
		return false;
	}
	
	public function validate(): void
	{
		$this->checkRequired(['name', 'source', 'target', 'sourceKey', 'targetKey']);
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
