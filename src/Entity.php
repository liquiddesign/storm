<?php

namespace StORM;

use StORM\Exception\InvalidStateException;
use StORM\Exception\NotExistsException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Relation;

abstract class Entity implements \JsonSerializable
{
	/**
	 * @var mixed[]
	 */
	protected $properties = [];
	
	/**
	 * @var string[]
	 */
	protected $foreignKeys = [];
	
	/**
	 * @var \StORM\Meta\Relation[]
	 */
	protected $relations = [];
	
	/**
	 * @var \StORM\CollectionEntity
	 */
	protected $parent;
	
	/**
	 * @var \StORM\Repository
	 */
	protected $repository;
	
	/**
	 * @var string
	 */
	protected $mutation;
	
	/**
	 * @var string[]
	 */
	protected $mutations;
	
	/**
	 * @var int|null
	 */
	protected $affectedNumber;
	
	/**
	 * Entity constructor.
	 * @param mixed[]|null $vars
	 * @param \StORM\Repository $repository
	 * @param string|null $mutation
	 * @param string[] $mutations
	 * @param \StORM\CollectionEntity $parent
	 * @param int|null $affectedNumber
	 */
	public function __construct(array $vars, Repository $repository, ?string $mutation = null, array $mutations = [], ?CollectionEntity $parent = null, ?int $affectedNumber = null)
	{
		foreach ($vars as $name => $value) {
			$this->$name = $value;
		}
	
		foreach ($repository->getStructure()->getRelations() as $name => $relation) {
			if ($relation->isKeyHolder()) {
				$this->foreignKeys[$name] = $this->$name ?? null;
			}
			
			unset($this->$name);
		}
		
		$this->mutation = $mutation;
		$this->mutations = $mutations;
		$this->parent = $parent;
		$this->repository = $repository;
		$this->affectedNumber = $affectedNumber;
	}
	
	public function getAffectedNumber(): ?int
	{
		return $this->affectedNumber;
	}
	
	/**
	 * Sets parents and affected number to null
	 * @internal
	 */
	public function removeParent(): void
	{
		$this->parent = null;
		$this->affectedNumber = null;
		
		return;
	}
	
	/**
	 * Load from array and filter mess in array by column names by default
	 * @param mixed[] $vars
	 * @param bool|null $filterByColumns
	 * @param $includePrimaryKey
	 */
	public function loadFromArray(array $vars, ?bool $filterByColumns = null, bool $includePrimaryKey = true): void
	{
		if ($filterByColumns) {
			$vars = $this->repository->filterByColumns($vars, $includePrimaryKey, $filterByColumns);
		}
		
		foreach ($vars as $name => $value) {
			$this->$name = $value;
		}
		
		return;
	}
	
	/**
	 * Load from entity objects and skip primary key
	 * @param \StORM\Entity $object
	 */
	public function loadFromObject(Entity $object): void
	{
		$vars = $this->repository->filterByColumns($object->toArray(), false);
		
		foreach ($vars as $name => $value) {
			$this->$name = $value;
		}
		
		return;
	}
	
	private function getPKName(): string
	{
		return $this->repository->getStructure()->getPK()->getPropertyName();
	}
	
	/**
	 * Get primary key value
	 * @return string
	 */
	public function getPK(): string
	{
		$pkName = $this->getPKName();
		
		if (isset($this->$pkName)) {
			return $this->$pkName;
		}
		
		if (isset($this->properties[$pkName])) {
			return $this->properties[$pkName];
		}
		
		throw new InvalidStateException(InvalidStateException::PK_IS_NOT_SET, $pkName);
	}
	
	/**
	 * Convert to string = return primary key value
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getPK();
	}
	
	/**
	 * Get relation from properities
	 * @param \StORM\Meta\Relation $relation
	 * @return \StORM\Entity
	 */
	protected function getRelationFromProperties(Relation $relation): Entity
	{
		$relatedClass = $relation->getTarget();
		$name = $relation->getName();
		$length = \strlen($name) + \strlen(Repository::RELATION_SEPARATOR);
		$data = [];
		
		foreach ($this->properties as $property => $value) {
			if (\strpos($property, $name . Repository::RELATION_SEPARATOR) === false) {
				continue;
			}
			
			$data[\substr($property, $length)] = $value;
		}
		
		$repository = $this->repository->getConnection()->getRepositoryByEntityClass($relatedClass);
		
		return new $relatedClass($data, $repository);
	}
	
	/**
	 * Get relation from relation
	 * @param \StORM\Meta\Relation $relation
	 * @return \StORM\CollectionRelation|\StORM\Entity|null
	 */
	protected function getRelation(Relation $relation)
	{
		if ($relation->isKeyHolder()) {
			$name = $relation->getName();
			
			if (!isset($this->foreignKeys[$name])) {
				return null;
			}
			
			if ($this->parent) {
				$object = $this->parent->getRelatedObject($relation, $this->foreignKeys[$name]);
				
				if ($object === null) {
					throw new NotFoundException($this->foreignKeys[$name]);
				}
				
				return $object;
			}
			
			return $this->repository->getConnection()->getRepositoryByEntityClass($relation->getTarget())->one($this->foreignKeys[$name], true);
		}
		
		return new CollectionRelation($this->repository, $relation, $this->getPK());
	}
	
	/**
	 * Set property
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set(string $name, $value): void
	{
		if (\array_key_exists($name, $this->foreignKeys)) {
			$this->foreignKeys[$name] = $value === null ? null : (string) $value;
		} else {
			$this->properties[$name] = $value;
		}
		
		return;
	}
	
	/**
	 * Set value to properties
	 * @param string $property
	 * @param mixed $value
	 * @param string|null $mutation
	 */
	public function setValue(string $property, $value, ?string $mutation = null): void
	{
		if (\array_key_exists($property, $this->foreignKeys)) {
			$this->foreignKeys[$property] = $value;
		}
		
		$vars = Helpers::getModelVars($this);
		
		if (isset($vars[$property]) && ($mutation === null || $mutation === $this->mutation)) {
			$this->$property = $value;
			
			if (\array_key_exists($property . Connection::MUTATION_SEPARATOR . $this->mutation, $this->properties)) {
				$this->properties[$property . Connection::MUTATION_SEPARATOR . $this->mutation] = $value;
			}
			
			return;
		}
		
		if ($mutation && $this->mutation) {
			if (!\in_array($mutation, $this->mutations)) {
				throw new NotExistsException(NotExistsException::MUTATION, $mutation);
			}
			
			$property .= Connection::MUTATION_SEPARATOR . $mutation;
		}
		
		$this->properties[$property] = $value;
		
		return;
	}
	
	/**
	 * Get value from properties
	 * @param string $property
	 * @param string|null $mutation
	 * @return mixed
	 */
	public function getValue(string $property, ?string $mutation = null)
	{
		if (\array_key_exists($property, $this->foreignKeys)) {
			return $this->foreignKeys[$property];
		}
		
		$vars = Helpers::getModelVars($this);
		
		if (isset($vars[$property]) && ($mutation === null || $mutation === $this->mutation)) {
			return $vars[$property];
		}
		
		if ($mutation && $this->mutation) {
			if (!\in_array($mutation, $this->mutations)) {
				throw new NotExistsException(NotExistsException::MUTATION, $mutation);
			}
			
			$property .= Connection::MUTATION_SEPARATOR . $mutation;
		}
		
		if (!\array_key_exists($property, $this->properties)) {
			throw new NotExistsException(NotExistsException::PROPERTY, $property);
		}
		
		return $this->properties[$property];
	}
	
	/**
	 * Get property
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name)
	{
		if (\array_key_exists($name, $this->properties)) {
			return $this->properties[$name];
		}
		
		if (\array_key_exists($name, $this->relations)) {
			return $this->relations[$name];
		}
		
		// init relation
		$relation = $this->repository->getStructure()->getRelation($name);
		
		if ($relation) {
			if ($relation->isKeyHolder() && isset($this->properties[$name . Repository::RELATION_SEPARATOR . $relation->getTargetKey()])) {
				return $this->relations[$name] = $this->getRelationFromProperties($relation);
			}
			
			return $this->relations[$name] = $this->getRelation($relation);
		}
		
		throw new NotExistsException(NotExistsException::PROPERTY, $name);
	}
	
	/**
	 * Isset property
	 * @param string $name
	 * @return mixed
	 */
	public function __isset(string $name)
	{
		return \array_key_exists($name, $this->properties);
	}
	
	
	/**
	 * Update entity object
	 * @param mixed[] $properties
	 * @param bool|null $filterByColumns
	 * @return int
	 */
	public function update(array $properties, ?bool $filterByColumns = null): int
	{
		foreach ($properties as $name => $value) {
			$this->$name = $value;
		}
		
		/** @var \StORM\ICollectionEntity $collection */
		$collection = $this->repository->many()->where($this->getPKName(), [$this->getPK()]);
		
		return $collection->update($properties, false, $filterByColumns);
	}
	
	/**
	 * @param bool $includeNonColumns
	 * @return mixed[]
	 */
	private function getVars(bool $includeNonColumns = false): array
	{
		$columns = $this->repository->getStructure()->getColumns(true, false);
		$columnNames = \array_keys($columns);
		$vars = Helpers::getModelVars($this);
		
		if ($this->mutation) {
			foreach ($columns as $name => $column) {
				if ($column->hasMutations()) {
					$this->properties[$name . Connection::MUTATION_SEPARATOR . $this->mutation] = $vars[$name];
					unset($vars[$name]);
				}
			}
		}
		
		return ($includeNonColumns ? $vars : \array_intersect_key($vars, \array_flip($columnNames))) + $this->properties + $this->foreignKeys;
	}
	
	/**
	 * Update all objects if $propertiesToUpdate is not null update certains columns
	 * @param string[]|null $propertiesToUpdate
	 * @return int
	 */
	public function updateAll(?array $propertiesToUpdate = null): int
	{
		$vars = $this->getVars(false);
		
		if ($propertiesToUpdate !== null) {
			$vars = \array_intersect_key($vars, \array_flip($propertiesToUpdate));
		}
		
		/** @var \StORM\CollectionEntity $collection */
		$collection = $this->repository->many()->where($this->getPKName(), [$this->getPK()]);
		
		return $collection->update($vars, false, false);
	}
	
	/**
	 * Delete coresponding row from database and return number affected affected rows
	 * @return int
	 */
	public function delete(): int
	{
		return $this->repository->many()->where($this->getPKName(), [$this->getPK()])->delete();
	}
	
	/**
	 * Convert entity to array. You can name relation to load. Collection are converted to array. If $expandRelations is set to null, all relation is loaded.
	 * @param string[] $relations
	 * @param bool $groupLocales
	 * @param bool $includeNonColumns
	 * @return mixed[]
	 */
	public function toArray(array $relations = [], bool $groupLocales = true, bool $includeNonColumns = false): array
	{
		$array = [$this->getPKName() => $this->getPK()] + $this->getVars($includeNonColumns);
		
		if ($this->mutation && $groupLocales) {
			foreach ($this->repository->getStructure()->getColumns() as $name => $column) {
				if ($column->hasMutations()) {
					$array[$name] = [];
					
					foreach ($this->mutations as $mutation) {
						$array[$name][$mutation] = $array[$name . Connection::MUTATION_SEPARATOR . $mutation] ?? null;
						unset($array[$name . Connection::MUTATION_SEPARATOR . $mutation]);
					}
				}
			}
		}
		
		foreach ($relations as $relationName) {
			$value = $this->getRelation($this->repository->getStructure()->getRelation($relationName));
			$array[$relationName] = $value instanceof CollectionRelation ? \array_keys($value->toArray()) : (string)$value;
		}
		
		return $array;
	}
	
	/**
	 * Convert all properties to array. All relations are loaded. Objects are converted to primary keys and collections to array of primary keys
	 * @return mixed[]
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray(\array_keys($this->repository->getStructure()->getRelations()), true, true);
	}
}
