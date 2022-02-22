<?php

declare(strict_types = 1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Strings;

class Relation extends AnnotationProperty
{
	/**
	 * @var class-string<\StORM\Entity>
	 */
	protected string $source;
	
	/**
	 * @var class-string<\StORM\Entity>
	 */
	protected string $target;
	
	protected string $sourceKey;
	
	protected string $targetKey;
	
	protected ?string $keyType;
	
	protected bool $isKeyHolder;
	
	protected bool $nullable = false;
	
	public function isLoaded(): bool
	{
		return isset($this->target) && isset($this->source);
	}
	
	/**
	 * @phpstan-return class-string<\StORM\Entity>
	 */
	public function getSource(): string
	{
		return $this->source;
	}
	
	/**
	 * @phpstan-param class-string<\StORM\Entity> $source
	 */
	public function setSource(string $source): void
	{
		$this->source = $source;
	}
	
	/**
	 * @phpstan-return class-string<\StORM\Entity>
	 */
	public function getTarget(): string
	{
		return $this->target;
	}
	
	/**
	 * @phpstan-param class-string<\StORM\Entity> $target
	 */
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
	
	public function getKeyType(): ?string
	{
		return $this->keyType;
	}
	
	public function setKeyType(?string $keyType): void
	{
		$this->keyType = $keyType;
	}
	
	public function isKeyHolder(): ?bool
	{
		return $this->isKeyHolder;
	}
	
	public function setKeyHolder(bool $holder): void
	{
		$this->isKeyHolder = $holder;
	}
	
	public function isNullable(): bool
	{
		return $this->nullable;
	}
	
	public function loadFromType(string $type): bool
	{
		$types = \explode('|', $type);
		$found = false;
		
		foreach ($types as $str) {
			$typeLower = Strings::lower($str);
			
			if ($typeLower === 'null') {
				$this->nullable = true;
				
				continue;
			}
			
			if ($found) {
				continue;
			}
			
			foreach (['/\<(.+)\>/', '/(.+)\[\]/'] as $reg) {
				if ($target = Strings::match($str, $reg)[1] ?? null) {
					break;
				}
			}
			
			$keyHolder = !$target;
			$target ??= $str;
			
			if (!Structure::isEntityClass($target)) {
				continue;
			}
			
			$this->target = Strings::substring($target, 0, 1) === '\\' ? Strings::substring($target, 1) : $target;
			$this->isKeyHolder = $keyHolder;
			
			$found = true;
		}
		
		return $found;
	}
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string(),
			'source' => Expect::string(),
			'target' => Expect::string(),
			'sourceKey' => Expect::string(),
			'targetKey' => Expect::string(),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'relation';
	}
}
