<?php

declare(strict_types = 1);

namespace StORM\Meta;

use Nette\Caching\Cache;
use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use StORM\Entity;
use StORM\Exception\AnnotationException;
use StORM\Exception\GeneralException;
use StORM\Exception\NotExistsException;
use StORM\Helpers;
use StORM\Repository;
use StORM\SchemaManager;

/**
 * @template T of \StORM\Entity
 */
class Structure
{
	public const NAME_SEPARATOR = '_';
	
	private const ANNOTATION_VAR = 'var';
	
	private const INTERFACE_PREFIX = 'I';
	
	private const ANNOTATION_TYPE_CLASS = 'class';
	
	private const ANNOTATION_TYPE_PROPERTY = 'property';
	
	/**
	 * @var class-string<T>
	 */
	private string $entityClass;
	
	private \StORM\Meta\Column $pk;
	
	private \StORM\Meta\Table $table;
	
	/**
	 * @var array<\StORM\Meta\Column>
	 */
	private ?array $columns = null;
	
	/**
	 * @var array<\StORM\Meta\Relation>
	 */
	private ?array $relations = null;
	
	/**
	 * @var array<array<mixed>>
	 */
	private array $customClassAnnotations = [];
	
	/**
	 * @var array<array<array<mixed>>>
	 */
	private array $customPropertyAnnotations = [];
	
	private \StORM\SchemaManager $schemaManager;
	
	private bool $hasMutations = false;
	
	private Cache $cache;
	
	private ?Column $defaultPK;
	
	/**
	 * @var array<mixed>
	 */
	private array $classDocComment;
	
	/**
	 * Table constructor.
	 * @param class-string<T> $class
	 * @param \StORM\SchemaManager $schemaManager
	 * @param \Nette\Caching\Cache $cache
	 * @param \StORM\Meta\Column|null $defaultPK
	 * @throws \Throwable
	 * @internal
	 */
	public function __construct(string $class, SchemaManager $schemaManager, Cache $cache, ?Column $defaultPK = null)
	{
		$this->entityClass = $class;
		$this->schemaManager = $schemaManager;
		$this->defaultPK = $defaultPK;
		$this->cache = $cache;
		
		try {
			$annotations = $schemaManager->getCustomAnnotations();
			
			$fileName = (new \ReflectionClass($class))->getFileName();
			
			$dataModel = $this;
			
			$auxData = $this->cache->load("$class-basic", static function (&$dependencies) use ($fileName, $dataModel, $annotations, $defaultPK) {
				$dependencies = [
					Cache::FILES => $fileName,
				];
				
				$classDocComment = $dataModel->getClassDocComment();
				$propertiesDocComments = $dataModel->getPropertiesDocComments();
				
				$table = $dataModel->loadTable($classDocComment);
				$pk = $dataModel->loadPK($propertiesDocComments);
				
				if (!$pk) {
					$pk = $defaultPK ?: $dataModel->generatePK($table->getName());
				}
				
				$dataModel->loadCustomAnnotations($annotations, $classDocComment, $propertiesDocComments);
				
				return [$table, $pk];
			});
			[$this->table, $this->pk] = $auxData;
		} catch (\ReflectionException $x) {
			throw new NotExistsException(null, NotExistsException::SCHEMA, $class);
		}
	}
	
	/**
	 * Get custom class annotation
	 * @param string $annotationName
	 * @return array<mixed>|null
	 */
	public function getClassAnnotation(string $annotationName): ?array
	{
		return $this->customClassAnnotations[Strings::lower($annotationName)] ?? null;
	}
	
	/**
	 * Get custom annotations for all properties
	 * @param string $annotationName
	 * @return array<array<mixed>>|null
	 */
	public function getPropertiesAnnotation(string $annotationName): ?array
	{
		return $this->customPropertyAnnotations[Strings::lower($annotationName)] ?? null;
	}
	
	/**
	 * Get custom annotations for property
	 * @param string $property
	 * @param string $annotationName
	 * @return array<mixed>|null
	 */
	public function getPropertyAnnotations(string $property, string $annotationName): ?array
	{
		return $this->customPropertyAnnotations[Strings::lower($annotationName)][$property] ?? null;
	}
	
	/**
	 * @return class-string<T>
	 */
	public function getEntityClass(): string
	{
		return $this->entityClass;
	}
	
	public function getTable(): Table
	{
		return $this->table;
	}
	
	public function getPK(): Column
	{
		return $this->pk;
	}
	
	/**
	 * @param string $expressionPrefix
	 * @param string $aliasPrefix
	 * @param string|null $mutation
	 * @param array<string>|null $fallbackColumns
	 * @return array<string>
	 */
	public function getColumnsSelect(string $expressionPrefix = '', string $aliasPrefix = '', ?string $mutation = null, ?array $fallbackColumns = null): array
	{
		if (!$this->getColumns()) {
			return ["$expressionPrefix*"];
		}
		
		$select = [];
		$locales = [];
		$connection = $this->schemaManager->getConnection();
		
		$pk = $this->getPK();
		$select[$aliasPrefix . $pk->getPropertyName()] = $expressionPrefix . $pk->getName();
		$mutation = $mutation ?: $connection->getMutation();
		$mutationSuffix = $connection->getAvailableMutations()[$mutation];
		$fallbackMutationSuffix = isset($connection->getFallbackMutations()[$mutation]) ? $connection->getAvailableMutations()[$connection->getFallbackMutations()[$mutation]] : null;
		
		foreach ($this->getColumns() as $column) {
			if ($column->hasMutations()) {
				$rawName = $expressionPrefix . $column->getName();
				
				$condition = $fallbackMutationSuffix && ($fallbackColumns === null || Arrays::contains($fallbackColumns, $column->getPropertyName()));
				$select[$aliasPrefix . $column->getPropertyName()] = $condition ? "COALESCE($rawName$mutationSuffix,$rawName$fallbackMutationSuffix)" : "$rawName$mutationSuffix";
				
				$locales[] = $column;
			} else {
				$select[$aliasPrefix . ($column->isForeignKey() ? $column->getName() : $column->getPropertyName())] = $expressionPrefix . $column->getName();
			}
		}
		
		foreach ($locales as $column) {
			foreach ($this->schemaManager->getConnection()->getAvailableMutations() as $suffix) {
				$localeColumn = $column->getName() . $suffix;
				$localeProperty = $column->getPropertyName() . $suffix;
				$select[$aliasPrefix . $localeProperty] = $expressionPrefix . $localeColumn;
			}
		}
		
		return $select;
	}
	
	/**
	 * @param bool $includePK
	 * @param bool $includeFK
	 * @return array<\StORM\Meta\Column>
	 */
	public function getColumns(bool $includePK = true, bool $includeFK = true): array
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		if ($this->columns === null) {
			throw new GeneralException('Init structure failed');
		}
		
		if ($includePK && $includeFK) {
			return $this->columns;
		}
		
		return \array_filter($this->columns, static function (Column $value) use ($includePK, $includeFK) {
			return ($includePK && !$value->isPrimaryKey()) || ($includeFK && !$value->isForeignKey());
		});
	}
	
	/**
	 * @return array<\StORM\Meta\Relation>
	 */
	public function getRelations(): array
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		if ($this->relations === null) {
			throw new GeneralException('Init structure failed');
		}
		
		return $this->relations;
	}
	
	/**
	 * Tells if has some mutation columns
	 */
	public function hasMutations(): bool
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		return $this->hasMutations;
	}
	
	public function hasColumn(string $name): bool
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		return isset($this->columns[$name]);
	}
	
	public function getColumn(string $name): ?Column
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		return $this->columns[$name] ?? null;
	}
	
	public function hasRelation(string $name): bool
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		return isset($this->relations[$name]);
	}
	
	public function getRelation(string $name): ?Relation
	{
		if (!$this->isInited()) {
			$this->init();
		}
		
		return $this->relations[$name] ?? null;
	}
	
	/**
	 * @return array<\StORM\Meta\Index>
	 * @throws \StORM\Exception\AnnotationException
	 * @throws \ReflectionException
	 */
	public function getIndexes(): array
	{
		$columns = $this->getColumns();
		$indexes = [];
		$class = $this->entityClass;
		
		foreach ($columns as $column) {
			if ($column->isUnique()) {
				$index = new Index($class);
				$index->setName($this->setPrefix((string) $column->getPropertyName()));
				$index->setUnique(true);
				$index->setMutations($column->hasMutations());
				$index->setColumns([(string) $column->getPropertyName()]);
				
				$indexes[$index->getName()] = $index;
			}
		}
		
		foreach ($this->getRelations() as $relation) {
			if ($relation->isKeyHolder()) {
				$index = new Index($class);
				$index->setName($this->setPrefix($relation->getName()));
				$index->setColumns([$relation->getSourceKey()]);
				$indexes[$index->getName()] = $index;
			}
		}
		
		$docComment = $this->getClassDocComment();
		
		if (!isset($docComment[Index::getAnnotationName()])) {
			return $indexes;
		}
		
		$indexDefinitions = $docComment[Index::getAnnotationName()];
		
		if (!\is_array($indexDefinitions)) {
			$indexDefinitions = [$indexDefinitions];
		}
		
		foreach ($indexDefinitions as $rawIndexDefinition) {
			$json = $this->parseJson($rawIndexDefinition);
			
			$index = new Index($class);
			$index->loadFromArray($json);
			$indexes[$index->getName()] = $index;
		}
		
		return $indexes;
	}
	
	/**
	 * @return array<\StORM\Meta\Trigger>
	 * @throws \StORM\Exception\AnnotationException
	 * @throws \ReflectionException
	 */
	public function getTriggers(): array
	{
		$triggers = [];
		$class = $this->entityClass;
		
		$docComment = $this->getClassDocComment();
		
		if (!isset($docComment[Trigger::getAnnotationName()])) {
			return [];
		}
		
		$triggerDefinitions = $docComment[Trigger::getAnnotationName()];
		
		if (!\is_array($triggerDefinitions)) {
			$triggerDefinitions = [$triggerDefinitions];
		}
		
		foreach ($triggerDefinitions as $rawTriggerDefinition) {
			$json = $this->parseJson($rawTriggerDefinition);
			
			$trigger = new Trigger($class);
			$trigger->loadFromArray($json);
			$triggers[$trigger->getName()] = $trigger;
		}
		
		return $triggers;
	}
	
	/**
	 * @return array<\StORM\Meta\Constraint>
	 * @throws \ReflectionException
	 */
	public function getConstraints(): array
	{
		$constraints = [];
		$class = $this->entityClass;
		
		$properties = $this->getPropertiesDocComments();
		
		foreach ($properties as $name => $docComment) {
			if (!isset($docComment[Constraint::getAnnotationName()])) {
				continue;
			}
			
			if (\is_array($docComment[Constraint::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class::$name", Constraint::getAnnotationName());
			}
			
			$json = $this->parseJson($docComment[Constraint::getAnnotationName()]);
		
			$relation = $this->getRelation($name);
			
			if (!$relation) {
				throw new AnnotationException(AnnotationException::STANDALONE_CONSTRAINT, "$class::$name");
			}
			
			if (!$relation->isKeyHolder()) {
				throw new AnnotationException(AnnotationException::NO_KEY_HOLDER_CONSTRAINT, "$class::$name");
			}
			
			$object = new Constraint($class, $relation->getPropertyName());
			$object->setDefaultsFromRelation($relation);
			//@TODO: vyresit jestli constraint je class nebo table
			//@phpstan-ignore-next-line
			$object->setSource($this->schemaManager->getStructure($object->getSource(), $this->cache, $this->defaultPK)->getTable()->getName());
			//@phpstan-ignore-next-line
			$object->setTarget($this->schemaManager->getStructure($object->getTarget(), $this->cache, $this->defaultPK)->getTable()->getName());
			$object->setName($this->setPrefix($name));
			$object->loadFromArray($json);
			
			$constraints[$object->getName()] = $object;
		}
		
		return $constraints;
	}
	
	/**
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	public function jsonSerialize(): array
	{
		$json = [
			'table' => $this->getTable()->jsonSerialize(),
			'columns' => [],
			'relations' => [],
			'constraints' => [],
			'indexes' => [],
			'triggers' => [],
		];
		
		foreach ($this->getColumns() as $column) {
			$json['columns'][] = $column->jsonSerialize();
		}
		
		foreach ($this->getRelations() as $relation) {
			$json['relations'][] = $relation->jsonSerialize();
		}
		
		foreach ($this->getConstraints() as $constraint) {
			$json['constraints'][] = $constraint->jsonSerialize();
		}
		
		foreach ($this->getIndexes() as $index) {
			$json['indexes'][] = $index->jsonSerialize();
		}
		
		foreach ($this->getTriggers() as $trigger) {
			$json['triggers'][] = $trigger->jsonSerialize();
		}
		
		return $json;
	}
	
	public static function isEntityClass(string $class): bool
	{
		return \is_subclass_of($class, \StORM\Entity::class);
	}
	
	public static function getRepositoryClassFromEntityClass(string $entityClass): string
	{
		return $entityClass . (new \ReflectionClass(Repository::class))->getShortName();
	}
	
	/**
	 * @param string $repositoryClass
	 * @return class-string<T>
	 */
	public static function getEntityClassFromInterface(string $repositoryClass): string
	{
		$class = Strings::substring($repositoryClass, Strings::length(self::INTERFACE_PREFIX));
		
		if (!\is_a($class, Entity::class, true)) {
			throw new GeneralException("Cannot load entity class from interface '$repositoryClass'");
		}
		
		return $class;
	}
	
	public static function getInterfaceFromRepositoryClass(string $repositoryClass): string
	{
		return self::INTERFACE_PREFIX . $repositoryClass;
	}
	
	/**
	 * @param string $repositoryClass
	 * @return class-string<T>
	 */
	public static function getEntityClassFromRepositoryClass(string $repositoryClass): string
	{
		$class = Strings::substring($repositoryClass, 0, Strings::indexOf($repositoryClass, (new \ReflectionClass(Repository::class))->getShortName(), -1));
		
		if (!\is_a($class, Entity::class, true)) {
			throw new GeneralException("Cannot parse $class from repository '$repositoryClass'");
		}
		
		return $class;
	}
	
	/**
	 * @param array<string> $customAnnotations
	 * @param array<string|int, string|array<string>> $classDocComment
	 * @param array<array<string|array<string>>> $propertiesDocComments
	 */
	protected function loadCustomAnnotations(array $customAnnotations, array $classDocComment, array $propertiesDocComments): void
	{
		foreach ($customAnnotations as $annotationName => $annotationType) {
			$annotationName = Strings::lower($annotationName);
			
			if ($annotationType === self::ANNOTATION_TYPE_CLASS && isset($classDocComment[$annotationName]) && \is_string($classDocComment[$annotationName])) {
				$this->customClassAnnotations[$annotationName] = $this->parseJson($classDocComment[$annotationName]);
			}
			
			if ($annotationType !== self::ANNOTATION_TYPE_PROPERTY) {
				continue;
			}

			foreach ($propertiesDocComments as $property => $propertyDocComment) {
				if (isset($propertyDocComment[$annotationName]) && \is_string($propertyDocComment[$annotationName])) {
					if (!isset($this->customPropertyAnnotations[$annotationName])) {
						$this->customPropertyAnnotations[$annotationName] = [];
					}
					
					$this->customPropertyAnnotations[$annotationName][$property] = $this->parseJson($propertyDocComment[$annotationName]);
				}
			}
		}
	}
	
	protected function generatePK(string $tableName): \StORM\Meta\Column
	{
		$columnName = $this->schemaManager->getPrimaryKeyName($tableName);
		$isAutoincrement = $this->schemaManager->isAutoincrement($tableName, $columnName);
		
		$pk = new Column($this->entityClass, null);
		$pk->setName($columnName);
		$pk->setPropertyName($columnName);
		$pk->setAutoincrement($isAutoincrement);
		
		return $pk;
	}
	
	/**
	 * @param array<string, array<int|string, string|array<string>>> $docComments
	 */
	protected function loadPK(array $docComments): ?Column
	{
		$class = $this->getEntityClass();
		
		foreach ($docComments as $name => $docComment) {
			if (!isset($docComment[Column::getAnnotationName()]) || !isset($docComment[Column::ANNOTATION_PK])) {
				continue;
			}
			
			if (isset($docComment[Column::getAnnotationName()]) && \is_array($docComment[Column::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class::$name", Column::getAnnotationName());
			}
			
			$pk = $this->loadColumn($name, $docComment);
			$pk->setPrimaryKey(true);
			
			return $pk;
		}
		
		return null;
	}
	
	/**
	 * @param array<array<string>>|array<array<array<string>>> $docComments
	 * @return array<\StORM\Meta\Column>
	 */
	protected function loadColumns(array $docComments): array
	{
		$properties = [];
		$pk = [];
		$class = $this->getEntityClass();
		
		foreach ($docComments as $name => $docComment) {
			if (!isset($docComment[Column::getAnnotationName()]) && !isset($docComment[Column::ANNOTATION_PK])) {
				continue;
			}
			
			if (isset($docComment[Column::getAnnotationName()]) && \is_array($docComment[Column::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class::$name", Column::getAnnotationName());
			}
			
			$property = $this->loadColumn($name, $docComment);
			
			if ($property->isPrimaryKey()) {
				$pk[$name] = $property;
			} else {
				$properties[$name] = $property;
			}
		}
		
		return $pk + $properties;
	}
	
	/**
	 * @param array<string, array<string|int, string|array<string>>> $docComments
	 * @param string $table
	 * @param \StORM\Meta\Column $pk
	 * @return array<\StORM\Meta\Relation>
	 * @throws \ReflectionException
	 */
	protected function loadRelations(array $docComments, string $table, Column $pk): array
	{
		$relations = [];
		$class = $this->getEntityClass();
		
		foreach ($docComments as $name => $docComment) {
			if (!isset($docComment[Relation::getAnnotationName()]) && !isset($docComment[RelationNxN::getAnnotationName()])) {
				continue;
			}
			
			if (isset($docComment[Relation::getAnnotationName()]) && \is_array($docComment[Relation::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class::$name", Relation::getAnnotationName());
			}
			
			if (isset($docComment[RelationNxN::getAnnotationName()]) && \is_array($docComment[RelationNxN::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class::$name", RelationNxN::getAnnotationName());
			}
			
			$relations[$name] = $this->loadRelation($name, $docComment, $table, $pk);
		}
		
		return $relations;
	}
	
	/**
	 * @param array<string|int, string|array<string>> $docComment
	 */
	protected function loadTable(array $docComment): Table
	{
		$class = $this->entityClass;
		
		$table = new Table($class);
		
		if (isset($docComment[Table::getAnnotationName()])) {
			if (\is_array($docComment[Table::getAnnotationName()])) {
				throw new AnnotationException(AnnotationException::MULTIPLE_ANNOTATION, "$class", Table::getAnnotationName());
			}
			
			$json = $this->parseJson($docComment[Table::getAnnotationName()]);
		
			$table->loadFromArray($json);
		}
		
		if (\is_string($docComment[0])) {
			$table->setComment($docComment[0] ?? '');
		}
		
		return $table;
	}
	
	/**
	 * @return array<string|int, string|array<string>>
	 * @throws \ReflectionException
	 */
	private function getClassDocComment(): array
	{
		if (isset($this->classDocComment)) {
			return $this->classDocComment;
		}
		
		$docComment = (new \ReflectionClass($this->entityClass))->getDocComment();
		
		if ($docComment === false) {
			return [];
		}
		
		return $this->classDocComment = Helpers::parseDocComment($docComment);
	}
	
	/**
	 * @return array<string, array<string|int, string|array<string>>>
	 * @throws \ReflectionException
	 */
	private function getPropertiesDocComments(): array
	{
		$properties = [];
		
		foreach (\get_class_vars($this->entityClass) as $name => $defaultValue) {
			$ref = new \ReflectionProperty($this->entityClass, $name);
			$docComment = $ref->getDocComment();
			
			if (!$docComment) {
				continue;
			}
			
			$properties[$name] = Helpers::parseDocComment($docComment);
			
			if (!isset($properties[$name]['default']) && $defaultValue !== null) {
				$properties[$name]['default'] = $defaultValue;
			}
			
			// @phpstan-ignore-next-line
			if (!$ref->hasType() || (\method_exists($ref->getType(), 'isBuiltin') && $ref->getType()->isBuiltin())) {
				continue;
			}
			
			$varAnnotation = $properties[$name][self::ANNOTATION_VAR] ?? '';
		
			/** @phpstan-ignore-next-line */
			$properties[$name][self::ANNOTATION_VAR] = $ref->getType()->getName() . ($varAnnotation ? "|$varAnnotation" : '') . ($ref->getType()->allowsNull() ? '|null' : '');
		}
		
		return $properties;
	}
	
	/**
	 * @param string $name
	 * @param array<string|array<string>> $parsedDocComment
	 */
	private function loadColumn(string $name, array $parsedDocComment): Column
	{
		$class = $this->entityClass;
		
		// @phpcs:ignore
		$json = isset($parsedDocComment[Column::getAnnotationName()]) && \is_string($parsedDocComment[Column::getAnnotationName()]) ? $this->parseJson($parsedDocComment[Column::getAnnotationName()]) : [];
		
		/** @var \ReflectionNamedType|null $realType */
		$realType = (new \ReflectionProperty($class, $name))->getType();
		
		$column = new Column($class, $name);
		$column->setName($name);
		
		if ($realType) {
			$column->setNullable($realType->allowsNull());
		}
		
		$column->setPropertyType(\is_string($parsedDocComment[self::ANNOTATION_VAR]) ? $parsedDocComment[self::ANNOTATION_VAR] : ($realType ? $realType->getName() : null));
		$column->loadFromArray($json);
		$column->setComment(\is_string($parsedDocComment[0]) ? $parsedDocComment[0] : '');
		
		if (isset($parsedDocComment[Column::ANNOTATION_PK])) {
			$column->setPrimaryKey(true);
		}
		
		if (!isset($json['name']) && isset($parsedDocComment[Relation::getAnnotationName()])) {
			$column->setName(Column::FOREIGN_KEY_PREFIX . $name);
		}
		
		if ($column->hasMutations()) {
			$this->hasMutations = true;
		}
		
		return $column;
	}
	
	private function setPrefix(string $name): string
	{
		return $this->getTable()->getName() . self::NAME_SEPARATOR . $name;
	}
	
	/**
	 * @param string $name
	 * @param array<string|array<string>> $parsedDocComment
	 * @param string $sourceTable
	 * @param \StORM\Meta\Column $sourcePk
	 * @throws \ReflectionException
	 */
	private function loadRelation(string $name, array $parsedDocComment, string $sourceTable, Column $sourcePk): Relation
	{
		$class = $this->entityClass;
		
		if (isset($parsedDocComment[RelationNxN::getAnnotationName()])) {
			$json = $parsedDocComment[RelationNxN::getAnnotationName()];
			$relationNxN = true;
		} else {
			$json = $parsedDocComment[Relation::getAnnotationName()];
			$relationNxN = false;
		}
		
		if (!\is_string($json)) {
			throw new AnnotationException(AnnotationException::INVALID_SCHEMA, $name, $sourceTable);
		}
		
		$json = $this->parseJson($json);
		
		$jsonType = $parsedDocComment[self::ANNOTATION_VAR] ?? null;
		
		$relation = $relationNxN ? new RelationNxN($class, $name) : new Relation($class, $name);
		$relation->setName($name);
		$relation->setSource($class);
		
		if (\is_string($jsonType)) {
			$loaded = $relation->loadFromType($jsonType);
			
			if ($loaded) {
				$target = $relation->getTarget();
				
				if ($relation instanceof RelationNxN) {
					$relation->setSourceKey($sourcePk->getName());
					$relation->setTargetKey($target === $class ? $sourcePk->getName() : $this->schemaManager->getStructure($target, $this->cache, $this->defaultPK)->getPK()->getName());
					$relation->setVia($sourceTable . RelationNxN::TABLE_NAME_GLUE . $this->schemaManager->getStructure($target, $this->cache, $this->defaultPK)->getTable()->getName());
					$relation->setSourceViaKey(Column::FOREIGN_KEY_PREFIX . Strings::lower((new \ReflectionClass($class))->getShortName()));
					$relation->setTargetViaKey(Column::FOREIGN_KEY_PREFIX . Strings::lower((new \ReflectionClass($target))->getShortName()));
					$relation->setSourceKeyType($sourcePk->getPropertyType());
					$relation->setTargetKeyType($this->schemaManager->getStructure($target, $this->cache, $this->defaultPK)->getPK()->getPropertyType());
				} elseif ($relation->isKeyHolder()) {
					$relation->setSourceKey($json['key'] ?? Column::FOREIGN_KEY_PREFIX . $name);
					$relation->setTargetKey($target === $class ? $sourcePk->getName() : $this->schemaManager->getStructure($target, $this->cache, $this->defaultPK)->getPK()->getName());
					$relation->setKeyType($target === $class ? $sourcePk->getPropertyType() : $this->schemaManager->getStructure($target, $this->cache, $this->defaultPK)->getPK()->getPropertyType());
				} else {
					$relation->setSourceKey($sourcePk->getName());
					$relation->setTargetKey($json['key'] ?? Column::FOREIGN_KEY_PREFIX . Strings::lower((new \ReflectionClass($class))->getShortName()));
					$relation->setKeyType($json['key'] ?? Column::FOREIGN_KEY_PREFIX . Strings::lower((new \ReflectionClass($class))->getShortName()));
				}
			}
		}
		
		$relation->loadFromArray($json);
		
		if (!$relation->isLoaded()) {
			throw new AnnotationException(AnnotationException::NOT_DEFINED_RELATION, $name);
		}
		
		if (\interface_exists($relation->getTarget())) {
			$relation->setTarget(self::getEntityClassFromInterface($relation->getTarget()));
		}
		
		if (\interface_exists($relation->getSource())) {
			$relation->setSource(self::getEntityClassFromInterface($relation->getSource()));
		}
		
		return $relation;
	}
	
	private function isInited(): bool
	{
		return $this->relations !== null && $this->columns !== null;
	}
	
	/**
	 * @throws \ReflectionException
	 */
	private function init(): void
	{
		$class = $this->entityClass;
		$fileName = (new \ReflectionClass($class))->getFileName();
		$dataModel = $this;
		
		$auxData = $this->cache->load("$class-init", static function (&$dependencies) use ($fileName, $dataModel) {
			$dependencies = [
				Cache::FILES => $fileName,
			];
			$propertiesDocComments = $dataModel->getPropertiesDocComments();
			$columns = [$dataModel->getPK()->getName() => $dataModel->getPK()] + $dataModel->loadColumns($propertiesDocComments);
			$relations = $dataModel->loadRelations($propertiesDocComments, $dataModel->getTable()->getName(), $dataModel->getPK());
			
			foreach ($relations as $relation) {
				if ($relation->isKeyHolder() && !isset($columns[$relation->getSourceKey()])) {
					$fk = new Column($dataModel->getEntityClass(), $relation->getName());
					$fk->setName($relation->getSourceKey());
					$fk->setNullable($relation->isNullable());
					$fk->setForeignKey(true);
					$fk->setPropertyType($relation->getKeyType());
					$columns[$relation->getName()] = $fk;
				}
			}
			
			return [$columns, $relations, $dataModel->hasMutations];
		});
		
		[$this->columns, $this->relations, $this->hasMutations] = $auxData;
	}
	
	/**
	 * @param string $string
	 * @return array<string>
	 */
	private function parseJson(string $string): array
	{
		return $string ? Json::decode($string, Json::FORCE_ARRAY) : [];
	}
}
