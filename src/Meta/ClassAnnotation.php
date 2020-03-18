<?php

namespace StORM\Meta;

use StORM\Exception\AnnotationException;

abstract class ClassAnnotation implements \JsonSerializable
{
	/**
	 * @var string
	 */
	protected $class;
	
	/**
	 * @var string
	 */
	protected $name;
	
	public function __construct(string $class)
	{
		$this->class = $class;
	}
	
	public function setName(string $name): void
	{
		$this->name = $name;
	}
	
	public function getName(): ?string
	{
		return $this->name;
	}
	
	public function getEntityClass(): ?string
	{
		return $this->class;
	}
	
	/**
	 * @param mixed[] $json
	 */
	public function loadFromArray(array $json): void
	{
		$class = $this->getEntityClass();
		$name = $this->getName();
		$annotation = $this->getAnnotationName();
		
		foreach ($json as $attribute => $value) {
			if (!\property_exists(static::class, $attribute)) {
				throw new AnnotationException(AnnotationException::ATTRIBUTE_NOT_EXISTS, "$class -> $name", "@$annotation -> $attribute");
			}
			
			$this->$attribute = (string) $value;
		}
	}
	
	/**
	 * @param string[] $required
	 */
	public function checkRequired(array $required): void
	{
		$class = $this->getEntityClass();
		$name = $this->getName();
		$annotation = $this->getAnnotationName();
		
		foreach ($required as $attribute) {
			if (\property_exists(static::class, $attribute) && !$this->$attribute) {
				throw new AnnotationException(AnnotationException::ATTRIBUTE_IS_EMPTY, "$class -> $name", "@$annotation -> $attribute");
			}
		}
		
		return;
	}

	abstract public function validate(): void;
	
	abstract public static function getAnnotationName(): string;
	
	/**
	 * @return string[]
	 */
	public function jsonSerialize(): array
	{
		$json = [];
		
		foreach ($this as $name => $value) {
			$json[$name] = $value;
		}
		
		return $json;
	}
}
