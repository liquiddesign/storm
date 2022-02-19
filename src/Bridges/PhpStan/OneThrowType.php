<?php

declare(strict_types = 1);

namespace StORM\Bridges\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use StORM\ICollection;

class OneThrowType implements \PHPStan\Type\DynamicMethodThrowTypeExtension
{
	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return \is_subclass_of($methodReflection->getDeclaringClass()->getName(), ICollection::class) && $methodReflection->getName() === 'first';
	}
	
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?\PHPStan\Type\Type
	{
		if (\count($methodCall->getArgs()) < 2) {
			return null;
		}
		
		$argType = $scope->getType($methodCall->getArgs()[1]->value);

		if ((new ConstantBooleanType(true))->isSuperTypeOf($argType)->yes()) {
			return $methodReflection->getThrowType();
		}
		
		return null;
	}
}
