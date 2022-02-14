<?php

declare(strict_types=1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Strings;

class Table extends AnnotationClass
{
	private const STRIP_NAMESPACES = ['DB\\', 'App\\'];
	
	protected ?string $collate = null;
	
	protected ?string $engine = null;
	
	protected string $comment = '';
	
	public function __construct(?string $class)
	{
		parent::__construct($class);
		
		$this->name = $this->getTableNameFromClass($class);
	}
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string(null),
			'engine' => Expect::string(null),
			'collate' => Expect::string(null),
			'comment' => Expect::string(null),
		]);
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
	
	public function setCollate(?string $collate): void
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
	
	public static function getAnnotationName(): string
	{
		return 'table';
	}
	
	private function getTableNameFromClass(string $model): string
	{
		$replaces = [];
		
		foreach (self::STRIP_NAMESPACES as $namespace) {
			$replaces["/$namespace/"] = '';
		}
		
		return Strings::lower(Strings::replace(Strings::replace($model, $replaces), '/\\/', '_'));
	}
}
