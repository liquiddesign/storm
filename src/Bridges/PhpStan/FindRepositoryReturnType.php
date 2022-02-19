<?php

declare(strict_types = 1);

namespace StORM\Bridges\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ConstantScalarType;
use StORM\DIConnection;
use StORM\Meta\Structure;
use StORM\Repository;

class FindRepositoryReturnType implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return DIConnection::class;
	}
	
	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'findRepository';
	}
	
	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): \PHPStan\Type\Type
	{
		unset($methodReflection);
		$paramNeedExpr = $methodCall->getArgs()[0]->value;
		$paramNeedType = $scope->getType($paramNeedExpr);
		
		if (!$paramNeedType instanceof ConstantScalarType) {
			return new \PHPStan\Type\ObjectType(Repository::class);
		}
		
		$entityClass = $paramNeedType->getValue();
		
		$repositoryClass = Structure::getRepositoryClassFromEntityClass($entityClass);
		$interface = Structure::getInterfaceFromRepositoryClass($repositoryClass);
		
		return new \PHPStan\Type\ObjectType(\interface_exists($interface) ? $interface : $repositoryClass);
	}
}
