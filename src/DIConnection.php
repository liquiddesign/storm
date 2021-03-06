<?php

namespace StORM;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use StORM\Meta\Structure;

class DIConnection extends \StORM\Connection
{
	private \Nette\DI\Container $container;
	
	private string $mutation;
	
	/**
	 * @var string[]
	 */
	private array $availableMutations = [];
	
	/**
	 * @var string[]
	 */
	private array $fallbackMutations = [];
	
	/**
	 * Connection constructor.
	 * @param \Nette\DI\Container $container
	 * @param string $name
	 * @param string $dsn
	 * @param string $user
	 * @param string $password
	 * @param int[] $attributes
	 */
	public function __construct(Container $container, string $name, string $dsn, string $user, string $password, array $attributes = [])
	{
		$this->container = $container;
		
		parent::__construct($name, $dsn, $user, $password, $attributes);
	}
	
	/**
	 * @deprecated Use find repository instead
	 * @phpstan-param class-string $entityClass
	 * @param string $entityClass
	 */
	public function getRepository(string $entityClass): Repository
	{
		return $this->findRepository($entityClass);
	}
	
	/**
	 * Return repository by entity class
	 * @phpstan-param class-string $entityClass
	 * @param string $entityClass
	 * @throws \Nette\DI\MissingServiceException
	 */
	public function findRepository(string $entityClass): Repository
	{
		if (!\class_exists($entityClass)) {
			throw new \InvalidArgumentException("$entityClass class not exists");
		}
		
		if (!\is_subclass_of($entityClass, Entity::class)) {
			throw new \InvalidArgumentException("$entityClass is not child of \StORM\Entity");
		}
		
		$repositoryClass = Structure::getRepositoryClassFromEntityClass($entityClass);
		$interface = Structure::getInterfaceFromRepositoryClass($repositoryClass);
		
		/** @var \StORM\Repository $repository */
		$repository = $this->container->getByType(\interface_exists($interface) ? $interface : $repositoryClass);
		
		return $repository;
	}
	
	/**
	 * Get all defined repositories names in container
	 * @return string[]
	 */
	public function findAllRepositories(): array
	{
		return $this->container->findByType(Repository::class);
	}
	
	public function findRepositoryByName(string $name): Repository
	{
		$repository = $this->container->getByName($name);
		
		if ($repository instanceof Repository === false) {
			throw new MissingServiceException("Missing repository '$name'");
		}
		
		return $repository;
	}
	
	/**
	 * @param string[] $fallbackMutations
	 */
	public function setFallbackMutations(array $fallbackMutations): void
	{
		$this->fallbackMutations = $fallbackMutations;
	}
	
	/**
	 * @return string[]
	 */
	public function getFallbackMutations(): array
	{
		return $this->fallbackMutations;
	}
	
	public function setMutation(string $mutation): void
	{
		if (!isset($this->availableMutations[$mutation])) {
			throw new \InvalidArgumentException("Mutation $mutation is not in available mutations");
		}
		
		$this->mutation = $mutation;
		
		return;
	}
	
	public function getMutation(): string
	{
		return $this->mutation;
	}
	
	public function getMutationSuffix(): string
	{
		return $this->availableMutations[$this->mutation] ?? '';
	}
	
	/**
	 * @deprecated Use getMutationSuffix instead
	 */
	public function getLangSuffix(): string
	{
		return $this->getMutationSuffix();
	}
	
	/**
	 * Get available mutations codes
	 * @return string[]
	 */
	public function getAvailableMutations(): array
	{
		return $this->availableMutations;
	}
	
	/**
	 * Set avalailable mutation codes => suffix
	 * @param string[] $mutations
	 */
	public function setAvailableMutations(array $mutations): void
	{
		$this->availableMutations = $mutations;
		$this->mutation = (string) \key($mutations);
	}
	
	public function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, string $prefix = ''): void
	{
		Helpers::bindVariables($property, $rawValue, $values, $binds, $varPrefix, $varPostfix, $this->getAvailableMutations(), $prefix);
		
		return;
	}
}
