<?php

declare(strict_types=1);

namespace StORM\Meta;

use Nette\Schema\Processor;
use Nette\Schema\Schema;

abstract class Annotation implements \JsonSerializable
{
	protected ?string $class;
	
	abstract public static function getAnnotationName(): string;
	
	abstract public function getSchema(): Schema;
	
	public function __construct(?string $class)
	{
		$this->class = $class;
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
		$processor = new Processor();
		$completed = $processor->process($this->getSchema(), $json);
	
		foreach ($completed as $attribute => $value) {
			if ($value === null) {
				continue;
			}
			
			\call_user_func_array([$this, 'set'. \ucfirst($attribute)], [$value]);
		}
	}
	
	/**
	 * @return string[]
	 */
	public function jsonSerialize(): array
	{
		$reflectionClass = new \ReflectionClass(static::class);
		$array = [];
		
		foreach ($reflectionClass->getProperties() as $property) {
			$property->setAccessible(true);
			$array[$property->getName()] = $property->getValue($this);
			$property->setAccessible(false);
		}
		
		return $array;
	}
}
