<?php

declare(strict_types=1);

namespace StORM\Meta;

class Table extends ClassAnnotation
{
	private const ANNOTATION = 'table';
	private const DEFAULT_ENTITY_NAMESPACE = 'DB';
	
	/**
	 * @var string
	 */
	protected $collate;
	
	/**
	 * @var string
	 */
	protected $engine;
	
	/**
	 * @var string
	 */
	protected $comment;
	
	
	public function __construct(string $class)
	{
		$this->name = $this->getTableNameFromClass($class);
		parent::__construct($class);
	}
	
	private function getTableNameFromClass(string $model): string
	{
		$db = self::DEFAULT_ENTITY_NAMESPACE;
		
		return \strtolower(\str_replace("\\", '_', \str_replace("$db\\", '', $model)));
	}
	
	public function getCollate(): ?string
	{
		return $this->collate;
	}
	
	public function getEngine(): ?string
	{
		return $this->engine;
	}
		
	public function setEngine(?string $engine): void
	{
		$this->engine = $engine;
	}
	
	public function setCollate(string $collate): void
	{
		$this->collate = $collate;
	}
	
	public function getComment(): string
	{
		return $this->comment;
	}
	
	public function setComment(string $comment): void
	{
		$this->comment = $comment;
	}

	
	public function validate(): void
	{
		$this->checkRequired(['name']);
		
		return;
	}
	
	public static function getAnnotationName(): string
	{
		return self::ANNOTATION;
	}
}
