<?php

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
	protected $charset;
	
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
	
	public function getCharset(): ?string
	{
		return $this->charset;
	}
	
	public function getEngine(): ?string
	{
		return $this->engine;
	}
	
	public function setCollate(string $collate): void
	{
		$this->collate = $collate;
	}
	
	public function setCharset(string $charset): void
	{
		$this->charset = $charset;
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
