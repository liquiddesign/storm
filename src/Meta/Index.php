<?php

declare(strict_types=1);

namespace StORM\Meta;

class Index extends ClassAnnotation
{
	private const ANNOTATION = 'index';
	
	/**
	 * Column names in array which index is on
	 * @var string[]
	 */
	protected $columns;
	
	/**
	 * Is a unique index?
	 * @var bool
	 */
	protected $unique = false;
	
	public function __construct(string $class)
	{
		parent::__construct($class);
	}

	/**
	 * @return string[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}
	
	/**
	 * @param string[] $columns
	 */
	public function setColumns(array $columns): void
	{
		$this->columns = $columns;
	}
	
	public function isUnique(): bool
	{
		return $this->unique;
	}
	
	public function setUnique(bool $unique): void
	{
		$this->unique = $unique;
	}

	public function addColumn(string $column): void
	{
		$this->columns[] = $column;
	}
	
	/**
	 * @param mixed[] $json
	 * @return void
	 */
	public function loadFromArray(array $json): void
	{
		if (isset($json['name'])) {
			$this->name = (string) $json['name'];
		}
		
		if (isset($json['unique'])) {
			$this->unique = (bool) $json['unique'];
		}
		
		if (isset($json['columns'])) {
			$this->columns = (array) $json['columns'];
		}
		
		return;
	}
	
	public function validate(): void
	{
		$this->checkRequired(['name', 'columns']);
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
