<?php

declare(strict_types=1);

namespace StORM\Meta;

class Column extends PropertyAnnotation
{
	public const FOREIGN_KEY_PREFIX = 'fk_';
	public const ANNOTATION_PK = 'pk';
	private const ANNOTATION = 'column';
	
	/**
	 * Name of the column
	 * @var string
	 */
	protected $propertyName;
	
	/**
	 * Type of the column
	 * @var string|null
	 */
	protected $propertyType;
	
	/**
	 * Sql type of column
	 * @var string|null
	 */
	protected $type;
	
	/**
	 * Default value of column
	 * @var string|null
	 */
	protected $default;
	
	/**
	 * Nullable true | false
	 * @var bool
	 */
	protected $nullable = false;
	
	/**
	 * Unique column true | false
	 * @var bool
	 */
	protected $unique = false;
	
	/**
	 * Mysql length of column
	 * @var string|int|null
	 */
	protected $length;
	
	/**
	 * Mutations true | false
	 * @var bool
	 */
	protected $mutations = false;

	/**
	 * @var string
	 */
	protected $extra = '';
	
	/**
	 * @var string
	 */
	protected $collate;
	
	/**
	 * @var string
	 */
	protected $comment = '';
	
	/**
	 * @var string|null
	 */
	private $charset;
	
	/**
	 * @var null|bool
	 */
	protected $autoincrement;
	
	/**
	 * @var bool
	 */
	protected $primaryKey = false;
	
	/**
	 * @var bool
	 */
	protected $foreignKey = false;
	
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

	public function setAutoincrement(bool $autoincrement): void
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

	public function hasMutations(): bool
	{
		return $this->mutations;
	}

	public function setMutations(bool $mutations): void
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

	public function getDefault(): ?string
	{
		return $this->default;
	}

	public function setDefault(?string $default): void
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
