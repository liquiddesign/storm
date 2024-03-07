<?php

declare(strict_types = 1);

namespace StORM;

/**
 * Class ArrayWrapper
 * @template T of \StORM\Entity
 * @implements \ArrayAccess<string|int, T>
 * @implements \Iterator<string|int, T>
 */
class ArrayWrapper implements \Iterator, \ArrayAccess, \Countable
{
	/**
	 * @var array<T>
	 */
	private array $source;
	
	/**
	 * @var \StORM\IEntityParent<T>
	 */
	private IEntityParent $parent;
	
	/**
	 * @var array<\StORM\IEntityParent<T>>
	 */
	private array $childParents;
	
	private bool $passRecursive;
	
	/**
	 * ArrayWrapper constructor.
	 * @param array<T> $source
	 * @param \StORM\IEntityParent<T> $parent
	 * @param array<\StORM\IEntityParent<T>> $childParents
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
	 * @return T|null
	 */
	public function current(): ?Entity
	{
		$current = \current($this->source);
		
		return $current ? $this->loadParent($current) : null;
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
	 */
	#[\ReturnTypeWillChange]
	public function key(): int|null|string
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
	 * @return T
	 */
	public function offsetGet($offset): Entity
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
	
	/**
	 * @param T $value
	 * @return T
	 */
	private function loadParent($value): Entity
	{
		if (!$value->hasParent()) {
			$value->setParent($this->parent);
			
			foreach ($this->childParents as $property => $parent) {
				$value->$property = new ArrayWrapper($value->$property, $parent, $this->passRecursive ? $this->childParents : [], $this->passRecursive);
			}
		}
		
		return $value;
	}
}
