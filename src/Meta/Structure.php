<?php

namespace StORM\Meta;

use Nette\Caching\Cache;
use StORM\Connection;
use StORM\Exception\AnnotationException;
use StORM\Exception\GeneralException;
use StORM\Helpers;
use StORM\Repository;
use StORM\SchemaManager;

class Structure
{
	private const ANNOTATION_VAR = 'var';
	
	private const INTERFACE_PREFIX = 'I';
	
	private const ANNOTATION_TYPE_CLASS = 'class';
	
	private const ANNOTATION_TYPE_PROPERTY = 'property';
	
	/**
	 * @var string
	 */
	private $entityClass;
	
	/**
	 * @var \StORM\Meta\Column
	 */
	private $pk;
	
	/**
	 * @var \StORM\Meta\Table
	 */
	private $table;
	
	/**
	 * @var \StORM\Meta\Column[]
	 */
	private $columns = [];
	
	/**
	 * @var \StORM\Meta\Relation[]
	 */
	private $relations = [];
	
	/**
	 * @var mixed[][]
	 */
	private $customClassAnnotations = [];
	
	/**
	 * @var mixed[][][]
	 */
	private $customPropertyAnnotations = [];
	
	/**
	 * @var \StORM\SchemaManager
	 */
	private $schemaManager;
	
	/**
	 * @var bool
	 */
	private $hasMutations = false;
	
	/**
	 * Table constructor.
	 * @internal
	 * @param string $class
	 * @param \StORM\SchemaManager $schemaManager
	 * @param \Nette\Caching\Cache $cache
	 * @throws \StORM\Exception\GeneralException
	 */
	public function __construct(string $class, SchemaManager $schemaManager, Cache $cache)
	{
		$this->entityClass = $class;
		$this->schemaManager = $schemaManager;
		
		try {
			$customAnnotations = $schemaManager->getConnection()->getCustomAnnotations();
			
			$fileName = (new \ReflectionClass($class))->getFileName();
			
			$dataModel = $this;
			
			[$this->table, $this->columns, $this->pk, $this->relations] = $cache->load("$class-meta", static function (&$dependencies) use ($fileName, $dataModel, $customAnnotations) {
				$dependencies = [
					Cache::FILES => $fileName,
				];
				
				$classDocComment = $dataModel->getClassDocComment();
				$propertiesDocComments = $dataModel->getPropertiesDocComments();
				
				$table = $dataModel->loadTable($classDocComment);
				$columns = $dataModel->loadColumns($propertiesDocComments);
				$firstColumn = \reset($columns);
				
				if ($firstColumn && $firstColumn->isPrimaryKey()) {
					$pk = $firstColumn;
				} else {
					$pk = $dataModel->loadPK($table->getName());
					$columns = [$pk->getName() => $pk] + $columns;
				}
				
				$relations = $dataModel->loadRelations($propertiesDocComments, $table->getName(), $pk->getName());
				
				foreach ($relations as $relation) {
					if ($relation->isKeyHolder()) {
						$fk = new Column($dataModel->getEntityClass(), $relation->getName());
						$fk->setName($relation->getSourceKey());
						$fk->setForeignKey(true);
						$columns[$relation->getName()] = $fk;
					}
				}
				
				$dataModel->loadCustomAnnotations($customAnnotations, $classDocComment, $propertiesDocComments);
				
				return [$table, $columns, $pk, $relations];
			});
		} catch (\ReflectionException $x) {
			throw new GeneralException("Cannot get $class reflection");
		}
	}
	
	/**
	 * Get custom class annotation
	 * @param string $annotationName
	 * @return mixed[]|null
	 */
	public function getClassAnnotation(string $annotationName): ?array
	{
		return $this->customClassAnnotations[\strtolower($annotationName)] ?? null;
	}
	
	/**
	 * Get custom annotations for all properties
	 * @param string $annotationName
	 * @return mixed[][]|null
	 */
	public function getPropertiesAnnotation(string $annotationName): ?array
	{
		return $this->customPropertyAnnotations[\strtolower($annotationName)] ?? null;
	}
	
	/**
	 * Get custom annotations for property
	 * @param string $property
	 * @param string $annotationName
	 * @return mixed[]|null
	 */
	public function getPropertyAnnotations(string $property, string $annotationName): ?array
	{
		return $this->customPropertyAnnotations[\strtolower($annotationName)][$property] ?? null;
	}
	
	/**
	 * @param string[] $customAnnotations
	 * @param string[]  $classDocComment
	 * @param string[][]  $propertiesDocComments
	 */
	protected function loadCustomAnnotations(array $customAnnotations, array $classDocComment, array $propertiesDocComments): void
	{
		foreach ($customAnnotations as $annotationName => $annotationType) {
			$annotationName = \strtolower($annotationName);
			
			if ($annotationType === self::ANNOTATION_TYPE_CLASS && isset($classDocComment[$annotationName])) {
				$this->customClassAnnotations[$annotationName] = $this->parseJson($classDocComment[$annotationName]);
			}
			
			if ($annotationType === self::ANNOTATION_TYPE_PROPERTY) {
				foreach ($propertiesDocComments as $property => $propertyDocComment) {
					if (isset($propertyDocComment[$annotationName])) {
						if (!isset($this->customPropertyAnnotations[$annotationName])) {
							$this->customPropertyAnnotations[$annotationName] = [];
						}
						
						$this->customPropertyAnnotations[$annotationName][$property] = $this->parseJson($propertyDocComment[$annotationName]);
					}
				}
			}
			
			continue;
		}
	}
	
	/**
	 * @return string[]
	 * @throws \ReflectionException
	 */
	protected function getClassDocComment(): array
	{
		return Helpers::parseDocComment((new \ReflectionClass($this->entityClass))->getDocComment());
	}
	
	/**
	 * @return string[]
	 * @throws \ReflectionException
	 */
	protected function getPropertiesDocComments(): array
	{
		$properties = [];
		
		foreach (\array_keys(\get_class_vars($this->entityClass)) as $name) {
			$properties[$name] = Helpers::parseDocComment((new \ReflectionProperty($this->entityClass, $name))->getDocComment());
		}
		
		return $properties;
	}
	
	public static function isEntityClass(string $class): bool
	{
		return \is_subclass_of($class, \StORM\Entity::class);
	}
	
	public static function getRepositoryClassFromEntityClass(string $entityClass): string
	{
		return $entityClass . \basename(Repository::class);
	}
	
	public static function getInterfaceFromRepositoryClass(string $repositoryClass): string
	{
		return self::INTERFACE_PREFIX . $repositoryClass;
	}
	
	public static function getEntityClassFromRepositoryClass(string $repositoryClass): string
	{
		return \substr($repositoryClass, 0, (\strrpos($repositoryClass, \basename(Repository::class))));
	}
	
	public function getEntityClass(): ?string
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
	 * @return string[]
	 */
	public function getColumnsSelect(string $expressionPrefix = '', string $aliasPrefix = ''): array
	{
		$activeLanguage = $this->schemaManager->getConnection()->getMutation();
		$languages = $this->schemaManager->getConnection()->getAvailableMutations();
		
		if (!$this->getColumns()) {
			return ["$expressionPrefix*"];
		}
		
		$select = [];
		$locales = [];
		$languageSeparator = Connection::MUTATION_SEPARATOR;
		
		$pk = $this->getPK();
		$select[$aliasPrefix . $pk->getPropertyName()] = $expressionPrefix . $pk->getName();
		
		foreach ($this->getColumns() as $column) {
			if ($column->hasMutations()) {
				$select[$aliasPrefix . $column->getPropertyName()] = $expressionPrefix . $column->getName() . $languageSeparator . $activeLanguage;
				$locales[] = $column;
			} else {
				$select[$aliasPrefix . $column->getPropertyName()] = $expressionPrefix . $column->getName();
			}
		}
		
		foreach ($locales as $column) {
			foreach ($languages as $language) {
				$localeColumn = $column->getName() . $languageSeparator . $language;
				$localeProperty = $column->getPropertyName() . $languageSeparator . $language;
				$select[$aliasPrefix . $localeProperty] = $expressionPrefix . $localeColumn;
			}
		}
		
		return $select;
	}
	
	protected function loadPK(string $tableName): \StORM\Meta\Column
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
	 * @param string[] $docComments
	 * @return \StORM\Meta\Column[]
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
	 * @param string[] $docComments
	 * @param string $table
	 * @param string $pk
	 * @return \StORM\Meta\Relation[]
	 */
	protected function loadRelations(array $docComments, string $table, string $pk): array
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
	 * @param bool $includePK
	 * @param bool $includeFK
	 * @return \StORM\Meta\Column[]
	 */
	public function getColumns(bool $includePK = true, bool $includeFK = true): array
	{
		if ($includePK && $includeFK) {
			return $this->columns;
		}
		
		return \array_filter($this->columns, static function (Column $value) use ($includePK, $includeFK) {
			return ($includePK && !$value->isPrimaryKey()) || ($includeFK && !$value->isForeignKey());
		});
	}
	
	/**
	 * @return \StORM\Meta\Relation[]
	 */
	public function getRelations(): array
	{
		return $this->relations;
	}
	
	/**
	 * @param string $name
	 * @param string[] $parsedDocComment
	 * @return \StORM\Meta\Column|null
	 */
	private function loadColumn(string $name, array $parsedDocComment): ?Column
	{
		$class = $this->entityClass;
		
		if (isset($parsedDocComment[Column::getAnnotationName()])) {
			$json = $this->parseJson($parsedDocComment[Column::getAnnotationName()]);
			
			if ($json === null) {
				throw new AnnotationException(AnnotationException::JSON_PARSE, "$class::$name", $parsedDocComment[Column::getAnnotationName()]);
			}
		} else {
			$json = [];
		}
		
		$column = new Column($class, $name);
		$column->setName($name);
		$column->setPropertyType($parsedDocComment[self::ANNOTATION_VAR] ?? null);
		$column->loadFromArray($json);
		$column->setComment($parsedDocComment[0] ?? '');
		
		if (isset($parsedDocComment[Column::ANNOTATION_PK])) {
			$column->setPrimaryKey(true);
		}
		
		if (!isset($json['name']) && isset($parsedDocComment[Relation::getAnnotationName()])) {
			$column->setName(Column::FOREIGN_KEY_PREFIX . $name);
		}
		
		if ($column->hasMutations()) {
			$this->hasMutations = true;
		}
		
		$column->validate();
		
		return $column;
	}
	
	/**
	 * Tells if has some mutation columns
	 * @return bool
	 */
	public function hasMutations(): bool
	{
		return $this->hasMutations;
	}
	
	/**
	 * @param string $name
	 * @param string[] $parsedDocComment
	 * @param string $sourceTable
	 * @param string $sourcePk
	 * @return \StORM\Meta\Relation
	 */
	private function loadRelation(string $name, array $parsedDocComment, string $sourceTable, string $sourcePk): ?Relation
	{
		$class = $this->entityClass;
		
		if (isset($parsedDocComment[RelationNxN::getAnnotationName()])) {
			$json = $parsedDocComment[RelationNxN::getAnnotationName()];
			$relationNxN = true;
		} else {
			$json = $parsedDocComment[Relation::getAnnotationName()];
			$relationNxN = false;
		}
		
		$json = $this->parseJson($json);
		
		if ($json === null) {
			throw new AnnotationException(AnnotationException::JSON_PARSE, "$class::$name", $json);
		}
		
		$jsonType = $parsedDocComment[self::ANNOTATION_VAR] ?? null;
		$relation = $relationNxN ? new RelationNxN($class, $name) : new Relation($class, $name);
		$relation->setName($name);
		$relation->setSource($class);
		
		if ($jsonType) {
			$loaded = $relation->loadFromType($jsonType);
			/** @var \StORM\Entity $target */
			$target = $relation->getTarget();
			
			if ($loaded) {
				if ($relation instanceof RelationNxN) {
					$relation->setSourceKey($sourcePk);
					$relation->setTargetKey($this->schemaManager->getStructure($target)->getPK()->getName());
					$relation->setVia($sourceTable . '_nxn_' . $this->schemaManager->getStructure($target)->getTable()->getName());
					$relation->setSourceViaKey(Column::FOREIGN_KEY_PREFIX . \strtolower(\basename($class)));
					$relation->setTargetViaKey(Column::FOREIGN_KEY_PREFIX . \strtolower(\basename($target)));
				} elseif ($relation->isKeyHolder()) {
					$relation->setSourceKey($json['key'] ?? Column::FOREIGN_KEY_PREFIX . $name);
					$relation->setTargetKey($this->schemaManager->getStructure($target)->getPK()->getName());
				} else {
					$relation->setSourceKey($sourcePk);
					$relation->setTargetKey($json['key'] ?? Column::FOREIGN_KEY_PREFIX . \strtolower(\basename($class)));
				}
			}
		}
		
		$relation->loadFromArray($json);
		
		$relation->validate();
		
		return $relation;
	}
	
	public function hasColumn(string $name): bool
	{
		return isset($this->columns[$name]);
	}
	
	public function getColumn(string $name): ?Column
	{
		return $this->columns[$name] ?? null;
	}
	
	public function hasRelation(string $name): bool
	{
		return isset($this->relations[$name]);
	}

	public function getRelation(string $name): ?Relation
	{
		return $this->relations[$name] ?? null;
	}
	
	/**
	 * @return \StORM\Meta\Index[]
	 * @throws \StORM\Exception\GeneralException
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
				$index->setName($column->getPropertyName());
				$index->setUnique(true);
				$index->setColumns([$column->getPropertyName()]);
				$index->validate();
				$indexes[$column->getPropertyName()] = $index;
			}
		}
		
		foreach ($this->getRelations() as $relation) {
			if ($relation->isKeyHolder()) {
				$index = new Index($class);
				$index->setName($relation->getPropertyName());
				$index->setColumns([$relation->getSourceKey()]);
				$index->validate();
				$indexes[$relation->getPropertyName()] = $index;
			}
		}
		
		$docComment = $this->getClassDocComment();
		
		if (!isset($docComment[Index::getAnnotationName()])) {
			return [];
		}
		
		$indexDefinitions = $docComment[Index::getAnnotationName()];
		
		if (!\is_array($indexDefinitions)) {
			$indexDefinitions = [$indexDefinitions];
		}
		
		foreach ($indexDefinitions as $rawIndexDefinition) {
			$json = $this->parseJson($rawIndexDefinition);
			
			if ($json === null) {
				throw new AnnotationException(AnnotationException::JSON_PARSE, $class, $rawIndexDefinition);
			}
			
			$index = new Index($class);
			$index->loadFromArray($json);
			$index->validate();
			$indexes[$index->getName()] = $index;
		}
		
		return $indexes;
	}
	
	/**
	 * @return \StORM\Meta\Trigger[]
	 * @throws \StORM\Exception\GeneralException
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
			
			if ($json === null) {
				throw new AnnotationException(AnnotationException::JSON_PARSE, $class, $rawTriggerDefinition);
			}
			
			$trigger = new Trigger($class);
			$trigger->loadFromArray($json);
			$trigger->validate();
			$triggers[$trigger->getName()] = $trigger;
		}
		
		return $triggers;
	}
	
	/**
	 * @return \StORM\Meta\Constraint[]
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
			
			if ($json === null) {
				throw new AnnotationException(AnnotationException::JSON_PARSE, "$class:$name", $docComment[Constraint::getAnnotationName()]);
			}
			
			$relation = $this->getRelation($name);
			
			if (!$relation) {
				throw new AnnotationException(AnnotationException::STANDALONE_CONSTRAINT, "$class::$name");
			}
			
			if (!$relation->isKeyHolder()) {
				throw new AnnotationException(AnnotationException::NO_KEY_HOLDER_CONSTRAINT, "$class::$name");
			}
			
			$object = new Constraint($class, $relation->getPropertyName());
			$object->setName($name);
			$object->setDefaultsFromRelation($relation);
			
			$object->loadFromArray($json);
			$object->validate();
			
			$constraints[$name] = $object;
		}
		
		return $constraints;
	}
	
	/**
	 * @param string[] $docComment
	 * @return \StORM\Meta\Table
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
			
			if ($json === null) {
				throw new AnnotationException(AnnotationException::JSON_PARSE, $class, $docComment);
			}
			
			$table->loadFromArray($json);
		}
		
		$table->setComment($docComment[0] ?? '');
		
		return $table;
	}
	
	/**
	 * @param $string
	 * @return string[]|null
	 */
	private function parseJson(string $string): ?array
	{
		return $string ? \json_decode($string, true) : [];
	}
	
	/**
	 * @return mixed[]
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
}
