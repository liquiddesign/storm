<?php

namespace StORM\Meta;

use StORM\Exception\AnnotationException;

class Constraint extends PropertyAnnotation
{
	public const ACTION_NO = 'NO ACTION';
	public const ACTION_SET_NULL = 'SET NULL';
	public const ACTION_CASCADE = 'CASCADE';
	private const ANNOTATION = 'constraint';
	
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
	 * @var string
	 */
	protected $onDelete;
	
	/**
	 * @var string
	 */
	protected $onUpdate;
	
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
	
	public function getOnDelete(): ?string
	{
		return $this->onDelete;
	}
	
	public function setOnDelete(?string $onDelete): void
	{
		$this->onDelete = $onDelete;
	}
	
	public function getOnUpdate(): ?string
	{
		return $this->onUpdate;
	}
	
	public function setOnUpdate(?string $onUpdate): void
	{
		$this->onUpdate = $onUpdate;
	}
	
	public function setDefaultsFromRelation(Relation $relation): void
	{
		$this->name = $relation->getName();
		$this->source = $relation->getSource();
		$this->sourceKey = $relation->getSourceKey();
		$this->target = $relation->getTarget();
		$this->targetKey = $relation->getTargetKey();
	}
	
	public function validate(): void
	{
		$class = $this->class;
		$name = $this->name;
		$annotation = $this->getAnnotationName();
		$allowedActions = [self::ACTION_CASCADE, self::ACTION_SET_NULL, self::ACTION_NO];
		$required = ['name', 'source', 'target', 'sourceKey', 'targetKey'];
		
		$this->checkRequired($required);
		
		if ($this->onDelete && !\in_array($this->onDelete, $allowedActions)) {
			throw new AnnotationException(AnnotationException::ATTRIBUTE_VALUE_NOT_ALLOWED, "$class -> $name", "@$annotation -> onDelete", \implode(',', $allowedActions));
		}
		
		if ($this->onUpdate && !\in_array($this->onUpdate, $allowedActions)) {
			throw new AnnotationException(AnnotationException::ATTRIBUTE_VALUE_NOT_ALLOWED, "$class -> $name", "@$annotation -> onUpdate", \implode(',', $allowedActions));
		}
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
