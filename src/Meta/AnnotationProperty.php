<?php

declare(strict_types = 1);

namespace StORM\Meta;

abstract class AnnotationProperty extends Annotation
{
	protected ?string $name;
	
	/**
	 * Name of the column
	 */
	protected ?string $propertyName;
	
	public function __construct(string $class, ?string $propertyName)
	{
		parent::__construct($class);
		
		$this->propertyName = $propertyName;
	}
	
	public function setName(string $name): void
	{
		$this->name = $name;
	}
	
	public function getName(): ?string
	{
		return $this->name;
	}
	
	public function setPropertyName(string $name): void
	{
		$this->propertyName = $name;
	}
	
	public function getPropertyName(): ?string
	{
		return $this->propertyName;
	}
}
