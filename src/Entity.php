<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Utils\Strings;
use StORM\Exception\InvalidStateException;
use StORM\Exception\NotExistsException;
use StORM\Exception\NotFoundException;
use StORM\Meta\Relation;
use StORM\Meta\Structure;

/**
 * Class Entity
 */
abstract class Entity implements \JsonSerializable, IDumper
{
	/**
	 * @var array<mixed>
	 */
	protected array $properties = [];
	
	/**
	 * @var array<string|int|null>
	 */
	protected array $foreignKeys = [];
	
	/**
	 * @var array<\StORM\Entity|\StORM\RelationCollection<\StORM\Entity>|null>
	 */
	protected array $relations = [];
	
	/**
	 * @template T of \StORM\Entity
	 * @var \StORM\IEntityParent<static>|null
	 */
	protected ?\StORM\IEntityParent $parent = null;
	
	protected ?string $activeMutation;
	
	/**
	 * @var array<string>
	 */
	protected array $availableMutations;
	
	/**
	 * Entity constructor.
	 * @param array<mixed> $vars
	 * @param \StORM\IEntityParent<self>|null $parent
	 * @param array<string> $mutations
	 * @param string|null $mutation
	 */
	public function __construct(array $vars, ?IEntityParent $parent = null, array $mutations = [], ?string $mutation = null)
	{
		if ($parent) {
			$this->setParent($parent, false);
		}
		
		$this->activeMutation = $mutation;
		$this->availableMutations = $mutations;
		
		foreach ($vars as $name => $value) {
			if (\is_array($value)) {
				foreach ($value as $mutation => $auxValue) {
					$this->setValue($name, $auxValue, $mutation);
				}
			} elseif (\is_string($name)) {
				$this->$name = $value;
			}
		}
	}
	
	/**
	 * Sets parents and affected number to null
	 * @internal
	 */
	public function removeParent(): void
	{
		$this->parent = null;
	}
	
	/**
	 * Gets if has parent
	 * @internal
	 */
	public function hasParent(): bool
	{
		return (bool) $this->parent;
	}
	
	/**
	 * @return \StORM\IEntityParent<static>
	 */
	public function getParent(): IEntityParent
	{
		if (!isset($this->parent)) {
			throw new NotExistsException($this, NotExistsException::SERIALIZE, 'setParent()');
		}
		
		return $this->parent;
	}
	
	/**
	 * @param \StORM\IEntityParent<static> $parent
	 * @param bool $recursive
	 */
	public function setParent(IEntityParent $parent, bool $recursive = true): void
	{
		if (isset($this->parent) && $this->parent === $parent) {
			return;
		}
		
		$this->parent = $parent;
		
		foreach ($parent->getRepository()->getStructure()->getRelations() as $name => $relation) {
			$fkName = $relation->getSourceKey();
			
			if ($relation->isKeyHolder() && !\array_key_exists($name, $this->foreignKeys)) {
				$this->foreignKeys[$name] = $this->properties[$fkName] ?? null;
				unset($this->properties[$fkName]);
			}
			
			if ($recursive && isset($this->relations[$name])) {
				if ($this->relations[$name] instanceof Entity) {
					$this->relations[$name]->setParent($this->getConnection()->findRepository($relation->getTarget()));
				}
				
				if ($this->relations[$name] instanceof Collection) {
					$this->relations[$name]->setRepository($this->getConnection()->findRepository($relation->getTarget()));
				}
			}
			
			unset($this->$name);
		}
	}
	
	public function isStored(): bool
	{
		return $this->parent instanceof ICollection;
	}
	
	/**
	 * Load from array and filter mess in array by column names by default
	 * @param array<mixed>|object $values
	 * @param bool|null $filterByColumns
	 * @param bool $includePrimaryKey
	 * @return array<mixed>
	 */
	public function loadFromArray($values, ?bool $filterByColumns = true, bool $includePrimaryKey = true): array
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		if (!\is_array($values)) {
			$type = \gettype($values);
			
			throw new \InvalidArgumentException("Input is not array or cannot be converted to array. $type given.");
		}
		
		if ($filterByColumns !== null) {
			$values = Helpers::filterInputArray($values, \array_keys($this->getStructure()->getColumns($includePrimaryKey)), $filterByColumns);
		}
		
		foreach ($values as $name => $value) {
			$column = $this->getStructure()->getColumn($name);
			
			if (\is_array($value) && $column && $column->hasMutations()) {
				foreach ($value as $mutation => $mutationValue) {
					$this->setValue($mutation, $mutationValue);
				}
				
				continue;
			}
			
			if ($value instanceof Literal) {
				continue;
			}
			
			$this->$name = $value;
		}
		
		return $values;
	}
	
	/**
	 * Load from entity objects and skip primary key
	 * @param \StORM\Entity $object
	 */
	public function loadFromEntity(Entity $object): void
	{
		$this->loadFromArray($object->toArray(), true, false);
	}
	
	/**
	 * Get primary key value by parent structure, if not set return first element
	 */
	public function getPK(): string|int
	{
		if (!$this->parent) {
			$firstElement = \reset($this->properties);
			
			if (!$firstElement) {
				throw new InvalidStateException($this, InvalidStateException::PK_IS_NOT_SET);
			}
			
			return $firstElement;
		}
		
		$pkName = $this->getStructure()->getPK()->getPropertyName();
		
		if (isset($this->$pkName)) {
			return $this->$pkName;
		}
		
		if (isset($this->properties[$pkName])) {
			return $this->properties[$pkName];
		}
		
		throw new InvalidStateException($this, InvalidStateException::PK_IS_NOT_SET, $pkName);
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
		
		if (isset($vars[$property]) && ($mutation === null || $mutation === $this->activeMutation)) {
			$this->$property = $value;
			
			if (\array_key_exists($property . $this->getMutationSuffix(), $this->properties)) {
				$this->properties[$property . $this->getMutationSuffix()] = $value;
			}
			
			return;
		}
		
		if ($mutation && $this->activeMutation) {
			if (!isset($this->availableMutations[$mutation])) {
				throw new NotExistsException($this, NotExistsException::MUTATION, $mutation, null, $this->availableMutations);
			}
			
			$property .= $this->getMutationSuffix($mutation);
		}
		
		$this->properties[$property] = $value;
	}
	
	/**
	 * Get value from properties
	 * @param string $property
	 * @param string|null $mutation
	 */
	public function getValue(string $property, ?string $mutation = null): mixed
	{
		if (\array_key_exists($property, $this->foreignKeys)) {
			return $this->foreignKeys[$property];
		}
		
		$vars = Helpers::getModelVars($this);
		$auxProperty = $property;
		$existsProperty = \array_key_exists($property, $vars);
		
		if ($existsProperty && ($mutation === null || $mutation === $this->activeMutation)) {
			return $vars[$property];
		}
		
		if ($mutation && $this->activeMutation) {
			if (!isset($this->availableMutations[$mutation])) {
				throw new NotExistsException($this, NotExistsException::MUTATION, $mutation, null, $this->availableMutations);
			}
			
			$property .= $this->getMutationSuffix($mutation);
		}
		
		if (!\array_key_exists($property, $this->properties)) {
			throw new NotExistsException($this, NotExistsException::VALUE, $property, static::class, $this->getHintProperties());
		}
		
		return $existsProperty ? Helpers::castScalar($this->properties[$property], \gettype($vars[$auxProperty])) : $this->properties[$property];
	}
	
	/**
	 * @param string $related
	 * @param array<mixed>|object $values
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function syncRelated(string $related, $values): Entity
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		$relationMeta = $this->getStructure()->getRelation($related);
		
		if (!$relationMeta) {
			throw new \InvalidArgumentException("$related is not entity relation");
		}
		
		if (!$this->$related) {
			$object = $this->getConnection()->findRepository($relationMeta->getTarget())->createOne($values);
			$this->update([$related => $object]);
			
			return $object;
		}
		
		$this->$related->update($values);
		
		return $this->$related;
	}
	
	/**
	 * Update entity object
	 * @param array<mixed>|array<array<mixed>>|object $values
	 * @param bool|null $filterByColumns
	 * @param bool $includePrimaryKey
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function update($values, ?bool $filterByColumns = true, bool $includePrimaryKey = true): int
	{
		if (\is_object($values)) {
			$values = Helpers::toArrayRecursive($values);
		}
		
		foreach (\array_keys($this->getStructure()->getRelations()) as $name) {
			if (!isset($values[$name]) || !\is_array($values[$name])) {
				continue;
			}
			
			$this->syncRelated($name, $values[$name]);
			
			unset($values[$name]);
		}
		
		$values = $this->loadFromArray($values, $filterByColumns, $includePrimaryKey);
		
		$values = $this->getParent()->getRepository()->propertiesToColumns($values, true);
		
		return $this->findMe()->update($values);
	}
	
	/**
	 * Update all properties and if $propertiesToUpdate is not null update certains columns
	 * @param array<string>|null $propertiesToUpdate
	 */
	public function updateAll(?array $propertiesToUpdate = null): int
	{
		$vars = $this->getVars();
		
		if ($propertiesToUpdate !== null) {
			$vars = \array_intersect_key($vars, \array_flip($propertiesToUpdate));
		}
		
		$vars = $this->getParent()->getRepository()->propertiesToColumns($vars, true);
		
		return $this->findMe()->update($vars);
	}
	
	/**
	 * Delete coresponding row from database and return number affected affected rows
	 */
	public function delete(): int
	{
		return $this->findMe()->delete();
	}
	
	/**
	 * Convert entity to array. You can name relation to load. Collection are converted to array. If $expandRelations is set to null, all relation is loaded.
	 * @param array<string>|array<string, mixed> $relations
	 * @param bool $groupLocales
	 * @param bool $includeNonColumns
	 * @param callable|null $relationCallback
	 * @return array<mixed>
	 */
	public function toArray(array $relations = [], bool $groupLocales = true, bool $includeNonColumns = false, bool $includePK = true, ?callable $relationCallback = null): array
	{
		$array = [$this->getStructure()->getPK()->getPropertyName() => $this->getPK()] + $this->getVars($includeNonColumns);
		
		if ($this->activeMutation && $groupLocales) {
			foreach ($this->getStructure()->getColumns() as $name => $column) {
				if ($column->hasMutations()) {
					$array[$name] = [];
					
					foreach ($this->availableMutations as $mutation => $suffix) {
						$array[$name][$mutation] = isset($array[$name . $suffix]) ? Helpers::castScalar($array[$name . $suffix], \gettype($this->$name)) : null;
						unset($array[$name . $suffix]);
					}
				}
			}
		}
		
		foreach ($relations as $key => $relationName) {
			try {
				if (\is_string($key)) {
					$default = $relationName;
					$relationName = $key;
				}
				
				$relation = $this->getStructure()->getRelation($relationName);
				
				if ($relation === null) {
					throw new NotExistsException($this, NotExistsException::RELATION, $relationName);
				}
				
				$value = $this->getRelation($relation);
				
				if ($value instanceof RelationCollection) {
					$array[$relationName] = !isset($default) ?
						\array_keys($value->toArray()) :
						(\is_callable($default) ? \call_user_func($default, $value) : \array_fill_keys(\array_keys($value->toArray()), $default));
				} elseif ($value instanceof Entity) {
					$array[$relationName] = isset($default) && \is_callable($default) ? \call_user_func($default, $value) : $value->toArray();
				} elseif ($value === null) {
					$array[$relationName] = $default ?? null;
				}
				
				if ($relationCallback) {
					$array[$relationName] = \call_user_func_array($relationCallback, [$array[$relationName]]);
				}
			} catch (NotFoundException $x) {
				unset($x);
			}
			
			unset($default);
		}
		
		if (!$includePK) {
			unset($array[$this->getStructure()->getPK()->getName()]);
		}
		
		return $array;
	}
	
	/**
	 * Convert entity to array. Empty references and empty array all converted to empty stdClass. JS do not support associative arrays
	 * @param array<string>|array<string, mixed> $relations
	 * @param bool $includeNonColumns
	 * @return array<mixed>
	 */
	public function toJsonArray(array $relations = [], bool $includeNonColumns = true): array
	{
		return $this->toArray($relations, true, $includeNonColumns, true, function ($value) {
			return (\is_array($value) && !$value) || $value === null ? new \stdClass() : $value;
		});
	}
	
	/**
	 * Convert all properties to array. None of relation is loaded. Objects are converted to primary keys and collections to array of primary keys
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array
	{
		return $this->toArray([], true, true);
	}
	
	/**
	 * Dump entity info
	 * @param bool $return
	 */
	public function dump(bool $return = false): ?string
	{
		$dump = '<br>';
		$dump .= '<strong>PROPERTIES:</strong> ';
		$dump .= \json_encode($this->properties, \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= '<strong>FOREIGN KEYS:</strong> ';
		$dump .= \json_encode($this->foreignKeys, \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= '<strong>RELATIONS:</strong> ';
		$dump .= \json_encode($this->relations, \JSON_PRETTY_PRINT);
		$dump .= '<hr>';
		$dump .= 'Parent: ' . ($this->parent ? \get_class($this->parent) : 'NULL') . '<br>';
		$dump .= 'Active mutation: ' . $this->activeMutation . '<br>';
		$dump .= 'Mutations: ' . \json_encode($this->availableMutations, \JSON_PRETTY_PRINT) . '<br>';
		
		if (!$return) {
			echo $dump;
			
			return null;
		}
		
		return $dump;
	}
	
	/**
	 * Get relation from relation\
	 * @param \StORM\Meta\Relation $relation
	 * @return \StORM\RelationCollection<\StORM\Entity>|\StORM\Entity|null
	 * @throws \StORM\Exception\NotFoundException
	 */
	protected function getRelation(Relation $relation): \StORM\RelationCollection|\StORM\Entity|null
	{
		if ($relation->isKeyHolder()) {
			$name = $relation->getName();
			
			if (!isset($this->foreignKeys[$name])) {
				if ($relation->getSourceKey() !== $this->getParent()->getRepository()->getStructure()->getPK()->getName()) {
					return null;
				}
				
				return $this->getConnection()->findRepository($relation->getTarget())->one([$relation->getTargetKey() => $this->getPK()]);
			}
			
			if ($this->parent instanceof Collection && $this->parent->isLoaded() && $this->parent->isOptimization()) {
				$object = $this->parent->getRelatedObject($relation, $this->foreignKeys[$name]);
				
				if ($object === null) {
					throw new NotFoundException($this->parent, [$name => $this->foreignKeys[$name]], $relation->getTarget());
				}
				
				return $object;
			}
			
			return $this->getConnection()->findRepository($relation->getTarget())->one($this->foreignKeys[$name], true);
		}
		
		/** @phpstan-ignore-next-line */
		return new RelationCollection($this->getConnection()->findRepository(static::class), $relation, $this->getPK());
	}
	
	protected function getConnection(): DIConnection
	{
		return $this->getParent()->getRepository()->getConnection();
	}
	
	/**
	 * @return \StORM\Meta\Structure<static>
	 */
	protected function getStructure(): Structure
	{
		return $this->getParent()->getRepository()->getStructure();
	}
	
	/**
	 * @return \StORM\Repository<static>
	 */
	protected function getRepository(): Repository
	{
		return $this->getParent()->getRepository();
	}
	
	/**
	 * @return \StORM\Collection<static>
	 */
	protected function findMe(): Collection
	{
		$pkName = $this->getParent()->getRepository()->getStructure()->getPK()->getName();
		
		return $this->getParent()->getRepository()->many()->setWhere($pkName, $this->getPK());
	}
	
	protected function getMutationSuffix(?string $mutation = null): string
	{
		return $this->availableMutations[$mutation ?: $this->activeMutation] ?? '';
	}
	
	/**
	 * @param bool $includeNonColumns
	 * @return array<mixed>
	 */
	private function getVars(bool $includeNonColumns = false): array
	{
		$columns = $this->getStructure()->getColumns(true, false);
		$columnNames = \array_keys($columns);
		$vars = Helpers::getModelVars($this);
		
		if ($this->activeMutation) {
			foreach ($columns as $name => $column) {
				if ($column->hasMutations()) {
					$this->properties[$name . $this->getMutationSuffix()] = $vars[$name];
					unset($vars[$name]);
				}
			}
		}
		
		return ($includeNonColumns ? $vars : \array_intersect_key($vars, \array_flip($columnNames))) + $this->properties + $this->foreignKeys;
	}
	
	/**
	 * Get relation from properities
	 * @param \StORM\Meta\Relation $relation
	 */
	private function getRelationFromProperties(Relation $relation): Entity
	{
		$relatedClass = $relation->getTarget();
		$name = $relation->getName();
		$length = Strings::length($name) + Strings::length(Repository::RELATION_SEPARATOR);
		$data = [];
		
		foreach ($this->properties as $property => $value) {
			if (!Strings::contains($property, $name . Repository::RELATION_SEPARATOR)) {
				continue;
			}
	
			$data[Strings::substring($property, $length)] = $value;
		}
		
		$repository = $this->getConnection()->findRepository($relatedClass);
		
		return new $relatedClass($data, $repository);
	}
	
	/**
	 * @return array<string>
	 */
	private function getHintProperties(): array
	{
		return \array_keys($this->foreignKeys) + \array_keys($this->relations) + \array_keys(Helpers::getModelVars($this)) + \array_keys($this->properties);
	}
	
	/**
	 * Get property
	 * @param string $name
	 * @throws \StORM\Exception\NotFoundException
	 */
	public function __get(string $name): mixed
	{
		if (\array_key_exists($name, $this->properties)) {
			return $this->properties[$name];
		}
		
		if (\array_key_exists($name, $this->relations)) {
			return $this->relations[$name];
		}
		
		// init relation
		$relation = $this->getStructure()->getRelation($name);
		
		if ($relation === null) {
			throw new NotExistsException($this, NotExistsException::VALUE, $name, static::class, $this->getHintProperties());
		}
		
		if ($relation->isKeyHolder() && isset($this->properties[$name . Repository::RELATION_SEPARATOR . $relation->getTargetKey()])) {
			return $this->relations[$name] = $this->getRelationFromProperties($relation);
		}
		
		return $this->relations[$name] = $this->getRelation($relation);
	}
	
	/**
	 * Isset property
	 * @param string $name
	 */
	public function __isset(string $name): bool
	{
		return \array_key_exists($name, $this->properties) || isset($this->foreignKeys[$name]);
	}
	
	/**
	 * Set property
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set(string $name, $value): void
	{
		if (\array_key_exists($name, $this->foreignKeys)) {
			if ($value === null) {
				$this->foreignKeys[$name] = null;
				unset($this->relations[$name]);
			}
			
			if (\is_scalar($value) || $value instanceof Entity) {
				$this->foreignKeys[$name] = (string) $value;
				unset($this->relations[$name]);
			}
			
			if ($value instanceof Entity || $value instanceof RelationCollection) {
				$this->relations[$name] = $value;
			}
		} else {
			$this->properties[$name] = $value;
		}
	}
	
	/**
	 * @param mixed $name
	 * @param mixed $arguments
	 */
	public function __call($name, $arguments): \StORM\IRelation
	{
		$property = Strings::firstLower(Strings::substring($name, 3));
		$relation = $this->getStructure()->getRelation($property);

		if (!$relation || $relation->isKeyHolder()) {
			throw new \BadMethodCallException('Call to undefined method ' . self::class . '::' . $name . '()');
		}
		
		$relation = new RelationCollection($this->getConnection()->findRepository(static::class), $relation, $this->getPK());
		
		if (\is_array($arguments) && Helpers::isAssociative($arguments)) {
			$relation->whereMatch($arguments);
		}
		
		return $relation;
	}
	
	/**
	 * Convert to string = return primary key value in string
	 */
	public function __toString(): string
	{
		try {
			return (string) $this->getPK();
		} catch (InvalidStateException $x) {
			unset($x);
			
			return '';
		}
	}
	
	/**
	 * @return array<string>
	 */
	public function __sleep(): array
	{
		$vars = \get_object_vars($this);
		unset($vars['parent']);
		
		return \array_keys($vars);
	}
	
	public function __wakeup(): void
	{
		$this->parent = null;
	}
}
