<?php

namespace StORM\Meta;

class Trigger extends ClassAnnotation
{
	private const ANNOTATION = 'trigger';
	
	/**
	 * Action to trigger
	 * @var string
	 */
	protected $manipulation;
	
	/**
	 * When to trigger action
	 * @var string
	 */
	protected $timing;
	
	/**
	 * Definition of the trigger
	 * @var string
	 */
	protected $statement;
	
	public function getManipulation(): string
	{
		return $this->manipulation;
	}
	
	public function setManipulation(string $manipulation): void
	{
		$this->manipulation = $manipulation;
	}

	public function getTiming(): string
	{
		return $this->timing;
	}
	
	public function setTiming(string $timing): void
	{
		$this->timing = $timing;
	}
	
	public function getStatement(): string
	{
		return $this->statement;
	}

	public function setStatement(string $statement): void
	{
		$this->statement = $statement;
	}
	
	public function validate(): void
	{
		$this->checkRequired(['name', 'manipulation', 'timing', 'statement']);
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
