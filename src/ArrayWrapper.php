<?php

declare(strict_types = 1);

namespace StORM;

/**
 * Class ArrayWrapper
 * @template T of object
 */
class ArrayWrapper implements \Iterator, \ArrayAccess, \Countable
{
	/**
	 * @var array<object>
	 */
	private array $source;
	
	private IEntityParent $parent;
	
	/**
	 * @var array<\StORM\IEntityParent>
	 */
	private array $childParents;
	
	private bool $passRecursive;
	
	/**
	 * ArrayWrapper constructor.
	 * @param array<object> $source
	 * @param \StORM\IEntityParent $parent
	 * @param array<\StORM\IEntityParent> $childParents
	 * @param bool $passRecursive
	 */
	public function __construct(array $source, IEntityParent $parent, array $childParents = [], bool $passRecursive = false)
	{
		$this->source = $source;
		$this->parent = $parent;
		$this->childParents = $childParents;
		$this->passRecursive = $passRecursive;
	}
	
	/**
	 * Return the current element
	 * @return \StORM\Entity|null
	 */
	public function current(): ?object
	{
		return $this->loadParent(\current($this->source));
	}
	
	/**
	 * Move forward to next element
	 */
	public function next(): void
	{
		\next($this->source);
	}
	
	/**
	 * Return the key of the current element
	 * @return string|int|null
	 */
	public function key()
	{
		return \key($this->source);
	}

	/**
	 * Checks if current position is valid
	 */
	public function valid(): bool
	{
		return \current($this->source) !== false;
	}
	
	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind(): void
	{
		\reset($this->source);
	}
	
	/**
	 * Whether a offset exists
	 * @param mixed $offset
	 */
	public function offsetExists($offset): bool
	{
		return isset($this->source[$offset]);
	}
	
	/**
	 * Offset to retrieve
	 * @param mixed $offset
	 * @return \StORM\Entity
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function offsetGet($offset): object
	{
		return $this->loadParent($this->source[$offset]);
	}
	
	/**
	 * Offset to set
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value): void
	{
		$this->source[$offset] = $value;
	}
	
	/**
	 * Offset to unset
	 * @param mixed $offset
	 */
	public function offsetUnset($offset): void
	{
		unset($this->source[$offset]);
	}
	
	public function count(): int
	{
		return \count($this->source);
	}
	
	private function loadParent(object $value): object
	{
		if ($value instanceof Entity && !$value->hasParent()) {
			$value->setParent($this->parent);
			
			foreach ($this->childParents as $property => $parent) {
				$value->$property = new ArrayWrapper($value->$property, $parent, $this->passRecursive ? $this->childParents : [], $this->passRecursive);
			}
		}
		
		return $value;
	}
}
