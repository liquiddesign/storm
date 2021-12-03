<?php

declare(strict_types=1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

class Column extends AnnotationProperty
{
	public const FOREIGN_KEY_PREFIX = 'fk_';
	public const ANNOTATION_PK = 'pk';
	
	/**
	 * Name of the column
	 */
	protected ?string $propertyName = null;
	
	/**
	 * Type of the column
	 */
	protected ?string $propertyType = null;
	
	/**
	 * Sql type of column
	 */
	protected ?string $type = null;
	
	/**
	 * Default value of column
	 * @var string|int|float|null
	 */
	protected $default;
	
	/**
	 * Nullable true | false
	 */
	protected bool $nullable = false;
	
	/**
	 * Unique column true | false
	 */
	protected bool $unique = false;
	
	/**
	 * Mysql length of column
	 * @var string|int|null
	 */
	protected $length;
	
	/**
	 * Mutations true | false
	 */
	protected bool $mutations = false;

	protected string $extra = '';
	
	protected string $attribute = '';
	
	protected ?string $collate = null;
	
	protected string $comment = '';
	
	protected ?bool $autoincrement = null;
	
	protected bool $primaryKey = false;
	
	protected bool $foreignKey = false;
	
	private ?string $charset = null;
	
	public function isForeignKey(): bool
	{
		return $this->foreignKey;
	}
	
	public function isPrimaryKey(): bool
	{
		return $this->primaryKey;
	}
	
	public function setPrimaryKey(bool $isPrimaryKey): void
	{
		$this->primaryKey = $isPrimaryKey;
	}
	
	public function setForeignKey(bool $isForeignKey): void
	{
		$this->foreignKey = $isForeignKey;
	}
	
	public function isAutoincrement(): ?bool
	{
		return $this->autoincrement;
	}

	public function setAutoincrement(?bool $autoincrement): void
	{
		$this->autoincrement = $autoincrement;
	}
	
	public function getExtra(): string
	{
		return $this->extra;
	}

	public function setExtra(string $extra): void
	{
		$this->extra = $extra;
	}
	
	public function getAttribute(): string
	{
		return $this->attribute;
	}
	
	public function setAttribute(string $attribute): void
	{
		$this->attribute = $attribute;
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
	 * @deprecated Use setMutations instead
	 * @param bool $mutations
	 */
	public function setLocale(bool $mutations): void
	{
		$this->mutations = $mutations;
	}
	
	/**
	 * @return string|int|null
	 */
	public function getLength()
	{
		return $this->length;
	}
	
	/**
	 * @param string|int|null $length
	 */
	public function setLength($length): void
	{
		$this->length = $length;
	}

	public function isUnique(): bool
	{
		return $this->unique;
	}

	public function setUnique(bool $unique): void
	{
		$this->unique = $unique;
	}
	
	public function isNullable(): bool
	{
		return $this->nullable;
	}

	public function setNullable(bool $nullable): void
	{
		$this->nullable = $nullable;
	}
	
	/**
	 * @return string|int|float|null
	 */
	public function getDefault()
	{
		return $this->default;
	}
	
	/**
	 * @param string|int|float|null $default
	 */
	public function setDefault($default): void
	{
		$this->default = $default;
	}
	
	public function getCollate(): ?string
	{
		return $this->collate;
	}
	
	public function setCollate(?string $collate): void
	{
		$this->collate = $collate;
	}
	
	public function getCharset(): ?string
	{
		return $this->charset;
	}
	
	public function setCharset(?string $charset): void
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

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setType(?string $type): void
	{
		$this->type = $type;
	}
	
	public function setPropertyType(?string $type): void
	{
		$this->propertyType = $type;
	}
	
	public function getPropertyType(): ?string
	{
		return $this->propertyType;
	}
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string(null),
			'type' => Expect::string(null),
			'nullable' => Expect::bool(null),
			'length' => Expect::type('string|int|null'),
			'default' => Expect::type('string|int|float|null'),
			'charset' => Expect::type('string|null'),
			'collate' => Expect::type('string|null'),
			'attribute' => Expect::string(null),
			'extra' => Expect::string(null),
			'comment' => Expect::string(null),
			'mutations' => Expect::bool(null),
			'locale' => Expect::bool(null),
			'autoincrement' => Expect::bool(null),
			'unique' => Expect::bool(null),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'column';
	}
}
