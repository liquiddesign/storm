<?php

declare(strict_types=1);

namespace StORM;

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
	 * @return string[]
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
		return \json_decode(\json_encode($input), true);
	}
	
	/**
	 * Tells if is associative array
	 * @param mixed[] $array
	 */
	public static function isAssociative(array $array): bool
	{
		return \array_keys($array) !== \range(0, \count($array) - 1);
	}
	
	/**
	 * Parse doc comment
	 * @param string $s
	 * @return string[][]|string[]|int[][]|int[]
	 */
	public static function parseDocComment(string $s): array
	{
		$options = [];
		
		if (!\preg_match('#^/\*\*(.*?)\*/#ms', $s, $content)) {
			return [];
		}
		
		if (\preg_match('#^[ \t*]*+([^\s@].*)#mi', $content[1], $matches)) {
			$options[0] = \trim($matches[1]);
		}
		
		\preg_match_all('#^[ \t*]*@(\w+)([^\w\r\n].*)?#mi', $content[1], $matches, \PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			$ref = &$options[\strtolower($match[1])];
			
			if (isset($ref)) {
				$ref = (array) $ref;
				$ref = &$ref[];
			}
			
			$ref = isset($match[2]) ? \trim($match[2]) : '';
		}
		
		return $options;
	}
	
	/**
	 * @param string $value
	 * @param string[] $possibilities
	 */
	public static function getBestSimilarString(string $value, array $possibilities): ?string
	{
		$best = null;
		$min = (\strlen($value) / 4 + 1) * 10 + .1;
		
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
	 * @return string|string[]|null
	 */
	public static function fancyString(string $s, bool $lower = true)
	{
		return \preg_replace('#[^a-z0-9]+#i', '_', \trim(($lower ? \strtolower($s) : $s), ' @'));
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
	 * @param string $prefix
	 * @param string[]|\StORM\ICollection[]|null $fragments
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
	 * @param mixed[] $values
	 * @param mixed[] $binds
	 * @param string $varPrefix
	 * @param string $varPostfix
	 * @param array $mutations
	 * @param string $prefix
	 */
	public static function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, array $mutations, string $prefix = ''): void
	{
		// cannot bind character "."
		$column = \str_replace('.', '_', $property);
		
		if (\is_array($rawValue)) {
			foreach ($rawValue as $mutation => $value) {
				if (!isset($mutations[$mutation])) {
					throw new \InvalidArgumentException("Language $mutation is not in available languages");
				}
				
				$realProperty = $column . $mutations[$mutation] ?? '';
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
			$binds[(string)$rawValue] = $prefix . $property;
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
	 * @param mixed[] $values
	 * @param string[] $columns
	 * @param bool $silent
	 * @return mixed[]
	 */
	public static function filterInputArray(array $values, array $columns, bool $silent = true): array
	{
		$mess = [];
		
		foreach ($values as $name => $value) {
			if (\in_array($name, $columns)) {
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
	 * @return mixed
	 */
	public static function castScalar($scalar, $type)
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
