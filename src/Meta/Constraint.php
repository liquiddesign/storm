<?php

declare(strict_types = 1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\SchemaManager;

class Constraint extends AnnotationProperty
{
	public const ACTION_NO = 'NO ACTION';
	public const ACTION_SET_NULL = 'SET NULL';
	public const ACTION_CASCADE = 'CASCADE';
	public const ACTION_RESTRICT = 'RESTRICT';

	protected string $source;

	protected string $target;
	
	protected string $sourceKey;
	
	protected string $targetKey;
	
	protected ?string $onDelete = null;
	
	protected ?string $onUpdate = null;
	
	public function getSource(): string
	{
		return $this->source;
	}

	public function setSource(string $source): void
	{
		$this->source = $source;
	}

	public function getTarget(): string
	{
		return $this->target;
	}

	public function setTarget(string $target): void
	{
		$this->target = $target;
	}
	
	public function getSourceKey(): string
	{
		return $this->sourceKey;
	}
	
	public function setSourceKey(string $sourceKey): void
	{
		$this->sourceKey = $sourceKey;
	}
	
	public function getTargetKey(): string
	{
		return $this->targetKey;
	}
	
	public function setTargetKey(string $targetKey): void
	{
		$this->targetKey = $targetKey;
	}
	
	public function getOnDelete(): ?string
	{
		return $this->onDelete;
	}
	
	public function setOnDelete(?string $onDelete): void
	{
		$this->onDelete = $onDelete;
	}
	
	public function getOnUpdate(): ?string
	{
		return $this->onUpdate;
	}
	
	public function setOnUpdate(?string $onUpdate): void
	{
		$this->onUpdate = $onUpdate;
	}
	
	public function setDefaultsFromRelation(Relation $relation): void
	{
		$this->name = $relation->getName();
		$this->source = $relation->getSource();
		$this->sourceKey = $relation->getSourceKey();
		$this->target = $relation->getTarget();
		$this->targetKey = $relation->getTargetKey();
	}
	
	public function setDefaultsFromRelationNxN(SchemaManager $schemaManager, RelationNxN $relation, string $type): void
	{
		if (!Arrays::contains(['source', 'target'], $type)) {
			throw new \InvalidArgumentException("Type can be 'source' or 'target', '$type' given.");
		}
		
		$glue = Strings::firstUpper($type);
		$viaCallback = [$relation, 'get' . $glue . 'ViaKey'];
		$keyCallback = [$relation, 'get' . $glue . 'Key'];
		$callback = [$relation, 'get' . $glue];
		
		if (!\is_callable($viaCallback) || !\is_callable($keyCallback) || !\is_callable($callback)) {
			throw new \InvalidArgumentException('Type cannot be mapped to method.');
		}
		
		/** @var class-string<\StORM\Entity> $class */
		$class = (string) \call_user_func($callback);
		
		$this->name = $relation->getVia() . \StORM\Meta\Structure::NAME_SEPARATOR . $type;
		$this->source = $relation->getVia();
		$this->sourceKey = \call_user_func($viaCallback);
		
		$this->target = $schemaManager->getStructure($class)->getTable()->getName();
		$this->targetKey = \call_user_func($keyCallback);
	}
	
	public function getSchema(): Schema
	{
		$allowedActions = [self::ACTION_CASCADE, self::ACTION_SET_NULL, self::ACTION_NO, self::ACTION_RESTRICT, null];
		
		return Expect::structure([
			'name' => Expect::string(),
			'source' => Expect::string(),
			'target' => Expect::string(),
			'sourceKey' => Expect::string(),
			'targetKey' => Expect::string(),
			'onUpdate' => Expect::anyOf(...$allowedActions)->default(null),
			'onDelete' => Expect::anyOf(...$allowedActions)->default(null),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'constraint';
	}
}
