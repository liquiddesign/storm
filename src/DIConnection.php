<?php

declare(strict_types = 1);

namespace StORM;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use StORM\Meta\Structure;

class DIConnection extends \StORM\Connection
{
	private \Nette\DI\Container $container;
	
	private string $mutation;
	
	/**
	 * @var array<string>
	 */
	private array $availableMutations = [];
	
	/**
	 * @var array<string>
	 */
	private array $fallbackMutations = [];
	
	/**
	 * Connection constructor.
	 * @param \Nette\DI\Container $container
	 * @param string $name
	 * @param string $dsn
	 * @param string $user
	 * @param string $password
	 * @param array<int> $attributes
	 */
	public function __construct(Container $container, string $name, string $dsn, string $user, string $password, array $attributes = [])
	{
		$this->container = $container;
		
		parent::__construct($name, $dsn, $user, $password, $attributes);
	}
	
	/**
	 * @deprecated Use find repository instead
	 * @template T of \StORM\Entity
	 * @phpstan-param class-string<T> $entityClass
	 * @param string $entityClass
	 * @return \StORM\Repository<T>
	 */
	public function getRepository(string $entityClass): Repository
	{
		return $this->findRepository($entityClass);
	}
	
	/**
	 * Return repository by entity class
	 * @template T of \StORM\Entity
	 * @phpstan-param class-string<T> $entityClass
	 * @throws \Nette\DI\MissingServiceException
	 * @return \StORM\Repository<T>
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
		
		/** @phpstan-ignore-next-line */
		return $this->container->getByType(\interface_exists($interface) ? $interface : $repositoryClass);
	}
	
	/**
	 * Get all defined repositories names in container
	 * @return array<string>
	 */
	public function findAllRepositories(): array
	{
		return $this->container->findByType(Repository::class);
	}
	
	/**
	 * @template T of \StORM\Entity
	 * @phpstan-param class-string<T> $name
	 * @return \StORM\Repository<T>
	 */
	public function findRepositoryByName(string $name): Repository
	{
		/** @var \StORM\Repository|object $repository */
		$repository = $this->container->getByName($name);
		
		if (!$repository instanceof Repository) {
			throw new MissingServiceException("Missing repository '$name'");
		}
		
		return $repository;
	}
	
	/**
	 * @param array<string> $fallbackMutations
	 */
	public function setFallbackMutations(array $fallbackMutations): void
	{
		$this->fallbackMutations = $fallbackMutations;
	}
	
	/**
	 * @return array<string>
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
	 * @return array<string>
	 */
	public function getAvailableMutations(): array
	{
		return $this->availableMutations;
	}
	
	/**
	 * Set avalailable mutation codes => suffix
	 * @param array<string> $mutations
	 */
	public function setAvailableMutations(array $mutations): void
	{
		$this->availableMutations = $mutations;
		$this->mutation = (string) \key($mutations);
	}
	
	/**
	 * @param string $property
	 * @param mixed $rawValue
	 * @param array<mixed> $values
	 * @param array<mixed> $binds
	 * @param string $varPrefix
	 * @param string $varPostfix
	 * @param string $prefix
	 */
	public function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, string $prefix = ''): void
	{
		Helpers::bindVariables($property, $rawValue, $values, $binds, $varPrefix, $varPostfix, $this->getAvailableMutations(), $prefix);
	}
}
