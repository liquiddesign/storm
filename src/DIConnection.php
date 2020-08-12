<?php

namespace StORM;

use Nette\DI\Container;
use StORM\Meta\Structure;

class DIConnection extends \StORM\Connection
{
	/**
	 * @var \Nette\DI\Container
	 */
	private $container;
	
	/**
	 * @var string
	 */
	private $mutation;
	
	/**
	 * @var string[]
	 */
	private $availableMutations = [];
	
	/**
	 * Connection constructor.
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}
	
	/**
	 * Return repository by entity class
	 * @param string $entityClass
	 * @return \StORM\Repository
	 * @throws \Nette\DI\MissingServiceException
	 */
	public function getRepository(string $entityClass): Repository
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
	public function getAllRepositories(): array
	{
		return $this->container->findByType(Repository::class);
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
	 * @return string
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
		$this->mutation = \key($mutations);
	}
	
	public function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix): void
	{
		Helpers::bindVariables($property, $rawValue, $values, $binds, $varPrefix, $varPostfix, $this->getAvailableMutations());
		
		return;
	}
}
