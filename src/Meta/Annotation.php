<?php

declare(strict_types = 1);

namespace StORM\Meta;

use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Utils\Strings;

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
	 * @param array<mixed> $json
	 */
	public function loadFromArray(array $json): void
	{
		$processor = new Processor();
		$completed = $processor->process($this->getSchema(), $json);
	
		foreach ($completed as $attribute => $value) {
			if ($value === null) {
				continue;
			}
			
			$callback = [$this, 'set' . Strings::firstUpper($attribute)];
			
			if (!\is_callable($callback)) {
				continue;
			}

			\call_user_func_array($callback, [$value]);
		}
	}
	
	/**
	 * @return array<string>
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
