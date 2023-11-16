<?php

declare(strict_types = 1);

namespace StORM\Bridges\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use StORM\ICollection;
use StORM\ISearchableCollection;

class FirstThrowType implements \PHPStan\Type\DynamicMethodThrowTypeExtension
{
	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		$class = $methodReflection->getDeclaringClass()->getName();

		return (\is_subclass_of($class, ICollection::class) || \is_subclass_of($class, ISearchableCollection::class)) && $methodReflection->getName() === 'first';
	}
	
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?\PHPStan\Type\Type
	{
		if (\count($methodCall->getArgs()) < 1) {
			return null;
		}
		
		$argType = $scope->getType($methodCall->getArgs()[0]->value);

		if ((new ConstantBooleanType(true))->isSuperTypeOf($argType)->yes()) {
			return $methodReflection->getThrowType();
		}
		
		return null;
	}
}
