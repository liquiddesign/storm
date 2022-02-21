<?php

declare(strict_types = 1);

namespace StORM;

use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use StORM\Exception\InvalidStateException;
use StORM\Exception\NotExistsException;

/**
 * Helpers class
 */
class Helpers
{
	/**
	 * Get model properties
	 * @param \StORM\Entity $object
	 * @return array<string>
	 */
	public static function getModelVars(Entity $object): array
	{
		return \get_object_vars($object);
	}
	
	/**
	 * @param mixed $input
	 * @return mixed
	 */
	public static function toArrayRecursive($input)
	{
		return Json::decode(Json::encode($input), Json::FORCE_ARRAY);
	}
	
	/**
	 * Tells if is associative array
	 * @param array<mixed> $array
	 */
	public static function isAssociative(array $array): bool
	{
		return \array_keys($array) !== \range(0, \count($array) - 1);
	}
	
	/**
	 * Parse doc comment
	 * @param string $s
	 * @return array<int|string|array<string|int>>
	 */
	public static function parseDocComment(string $s): array
	{
		$options = [];
		
		if (!\preg_match('#^/\*\*(.*?)\*/#ms', $s, $content)) {
			return [];
		}
		
		if (\preg_match('#^[ \t*]*+([^\s@].*)#mi', $content[1], $matches)) {
			$options[0] = Strings::trim($matches[1]);
		}
		
		\preg_match_all('#^[ \t*]*@(\w+)([^\w\r\n].*)?#mi', $content[1], $matches, \PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			$ref = &$options[Strings::lower($match[1])];
			
			if (isset($ref)) {
				$ref = (array) $ref;
				$ref = &$ref[];
			}
			
			$ref = isset($match[2]) ? Strings::trim($match[2]) : '';
		}
		
		return $options;
	}
	
	/**
	 * @param string $value
	 * @param array<string> $possibilities
	 */
	public static function getBestSimilarString(string $value, array $possibilities): ?string
	{
		$best = null;
		$min = (Strings::length($value) / 4 + 1) * 10 + .1;
		
		foreach (\array_unique($possibilities, \SORT_REGULAR) as $item) {
			if ($item !== $value && (
					$len = \levenshtein($item, $value, 10, 11, 10)) < $min
				) {
				$min = $len;
				$best = $item;
			}
		}
		
		return $best;
	}
	
	/**
	 * Converts to web safe characters [a-z0-9] and trim @ and ' '
	 * @param string $s
	 * @param bool $lower
	 * @return string|array<string>|null
	 */
	public static function fancyString(string $s, bool $lower = true)
	{
		return \preg_replace('#[^a-z0-9]+#i', '_', Strings::trim(($lower ? Strings::lower($s) : $s), ' @'));
	}
	
	/**
	 * Return if is valid SQL identifier
	 * @param string $name
	 */
	public static function isValidIdentifier(string $name): bool
	{
		return (bool) \preg_match('/[a-z_\-0-9]/i', $name);
	}
	
	/**
	 * Create SQL clause string
	 * @template T of object
	 * @param string $prefix
	 * @param array<string|int>|array<\StORM\ICollection<T>>|null $fragments
	 * @param string $glue
	 * @param string $assocGlue
	 * @param bool $brackets
	 * @param bool $reverse
	 */
	public static function createSqlClauseString(string $prefix, ?array $fragments, string $glue, string $assocGlue = '', bool $brackets = false, bool $reverse = false): string
	{
		if ($fragments === null || \count($fragments) === 0) {
			return '';
		}
		
		$i = 0;
		$string = '';
		
		foreach ($fragments as $k => $v) {
			if ($i !== 0) {
				$string .= $glue;
			}
			
			if ($v instanceof ICollection) {
				$v->setIndex(null);
			}
			
			$string .= \is_int($k) ? $v : (!$reverse ? "$v$assocGlue$k" : "$k$assocGlue$v");
			
			$i++;
		}
		
		return $brackets ? "$prefix ($string)" : "$prefix $string";
	}
	
	/**
	 * @param string $property
	 * @param mixed $rawValue
	 * @param array<mixed> $values
	 * @param array<mixed> $binds
	 * @param string $varPrefix
	 * @param string $varPostfix
	 * @param array<string, string> $mutations
	 * @param string $prefix
	 */
	public static function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, array $mutations, string $prefix = ''): void
	{
		// cannot bind character "."
		$column = Strings::replace($property, '/\./', '_');
		
		if (\is_array($rawValue)) {
			foreach ($rawValue as $mutation => $value) {
				if (!isset($mutations[$mutation])) {
					throw new \InvalidArgumentException("Language $mutation is not in available languages");
				}
				
				$realProperty = $column . ($mutations[$mutation] ?? '');
				$values["$varPrefix$realProperty$varPostfix"] = \is_bool($value) ? (int) $value : $value;
				$binds[":$varPrefix$realProperty$varPostfix"] = $prefix . $realProperty;
			}
			
			return;
		}
		
		if (\is_scalar($rawValue) || $rawValue === null) {
			$values["$varPrefix$column$varPostfix"] = \is_bool($rawValue) ? (int) $rawValue : $rawValue;
			$binds[":$varPrefix$column$varPostfix"] = $prefix . $property;
			
			return;
		}
		
		if ($rawValue instanceof Literal) {
			$binds[(string) $rawValue] = $prefix . $property;
			
			return;
		}
		
		if ($rawValue instanceof ICollection) {
			$binds[(string) $rawValue] = $prefix . $property;
			$values += $rawValue->getVars();
			
			return;
		}
		
		if (\is_object($rawValue) && \method_exists($rawValue, '__toString')) {
			$values["$varPrefix$column$varPostfix"] = (string) $rawValue;
			$binds[":$varPrefix$column$varPostfix"] = $prefix . $property;
			
			return;
		}
		
		$type = \is_object($rawValue) ? \get_class($rawValue) : \gettype($rawValue);
		
		throw new InvalidStateException(null, InvalidStateException::INVALID_BINDER_VAR, "$property of $type");
	}
	
	/**
	 * @param array<mixed> $values
	 * @param array<string> $columns
	 * @param bool $silent
	 * @return array<mixed>
	 */
	public static function filterInputArray(array $values, array $columns, bool $silent = true): array
	{
		$mess = [];
		
		foreach ($values as $name => $value) {
			if (Arrays::contains($columns, $name)) {
				continue;
			}
			
			$mess[$name] = $value;
			unset($values[$name]);
		}
		
		if (!$silent && $mess) {
			foreach (\array_keys($mess) as $property) {
				throw new NotExistsException(null, NotExistsException::PROPERTY, $property, 'array', $columns);
			}
		}
		
		return $values;
	}
	
	/**
	 * @param mixed $scalar
	 * @param string $type
	 * @return mixed
	 */
	public static function castScalar($scalar, string $type)
	{
		if ($type === 'boolean') {
			return \boolval($scalar);
		}
		
		if ($type === 'integer') {
			return \intval($scalar);
		}
		
		if ($type === 'double') {
			return \floatval($scalar);
		}
		
		return $scalar;
	}
}
