<?php

declare(strict_types = 1);

namespace StORM\Bridges\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\TypeCombinator;
use StORM\ICollection;

class FirstReturnType implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return ICollection::class;
	}
	
	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'first';
	}
	
	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): \PHPStan\Type\Type
	{
		$type = \PHPStan\Reflection\ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $methodReflection->getVariants())->getReturnType();
		
		if (isset($methodCall->getArgs()[0])) {
			$argType = $scope->getType($methodCall->getArgs()[0]->value);
			
			if ((new ConstantBooleanType(true))->isSuperTypeOf($argType)->yes()) {
				return TypeCombinator::removeNull($type);
			}
		}
		
		return $type;
	}
}
