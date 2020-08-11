<?php

declare(strict_types=1);

namespace StORM\Meta;

abstract class AnnotationClass extends Annotation
{
	/**
	 * @var string|null
	 */
	protected $name;
	
	public function setName(string $name): void
	{
		$this->name = $name;
	}
	
	public function getName(): ?string
	{
		return $this->name;
	}
}
