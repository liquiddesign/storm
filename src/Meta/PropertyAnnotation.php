<?php

namespace StORM\Meta;

abstract class PropertyAnnotation extends ClassAnnotation
{
	/**
	 * Name of the column
	 * @var string
	 */
	protected $propertyName;
	
	public function __construct(string $class, ?string $propertyName)
	{
		parent::__construct($class);
		$this->propertyName = $propertyName;
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
