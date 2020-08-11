<?php

declare(strict_types=1);

namespace StORM\Meta;

abstract class AnnotationProperty extends Annotation
{
	/**
	 * @var string|null
	 */
	protected $name;
	
	/**
	 * Name of the column
	 * @var string|null
	 */
	protected $propertyName;
	
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
