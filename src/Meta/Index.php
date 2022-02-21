<?php

declare(strict_types = 1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

class Index extends AnnotationClass
{
	/**
	 * Column names in array which index is on
	 * @var array<string>
	 */
	protected array $columns;
	
	/**
	 * Is a unique index?
	 */
	protected bool $unique = false;
	
	/**
	 * Mutations true | false
	 */
	protected bool $mutations = false;
	
	/**
	 * @template T of \StORM\Entity
	 * @param class-string<T> $class
	 */
	public function __construct(string $class)
	{
		parent::__construct($class);
	}
	
	public function hasMutations(): bool
	{
		return $this->mutations;
	}
	
	public function setMutations(bool $mutations): void
	{
		$this->mutations = $mutations;
	}

	/**
	 * @return array<string>
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}
	
	/**
	 * @param array<string> $columns
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
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string()->required(),
			'columns' => Expect::listOf('string')->min(1),
			'unique' => Expect::bool(false),
			'mutations' => Expect::bool(null),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'index';
	}
}
