<?php

namespace StORM;

use StORM\Exception\InvalidStateException;

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
	 * Tells if is associative array
	 * @param mixed[] $array
	 * @return bool
	 */
	public static function isAssociative(array $array): bool
	{
		return \array_keys($array) !== \range(0, \count($array) - 1);
	}
	
	/**
	 * Parse doc comment
	 * @param string $s
	 * @return string[]
	 */
	public static function parseDocComment(string $s): array
	{
		$options = [];
		
		if (!\preg_match('#^/\*\*(.*?)\*/#ms', $s, $content)) {
			return [];
		}
		
		if (\preg_match('#^[ \t\*]*+([^\s@].*)#mi', $content[1], $matches)) {
			$options[0] = \trim($matches[1]);
		}
		
		\preg_match_all('#^[ \t\*]*@(\w+)([^\w\r\n].*)?#mi', $content[1], $matches, \PREG_SET_ORDER);
		
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
	 * @return string|null
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
	 * @return bool
	 */
	public static function isValidIdentifier(string $name): bool
	{
		return \preg_match('/[a-z_\-0-9]/i', $name);
	}
	
	/**
	 * Create SQL clause string
	 * @param string $prefix
	 * @param string[]|null $fragments
	 * @param string $glue
	 * @param string $assocGlue
	 * @param bool $brackets
	 * @param bool $reverse
	 * @return string
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
	 * @param string[] $mutations
	 */
	public static function bindVariables(string $property, $rawValue, array &$values, array &$binds, string $varPrefix, string $varPostfix, array $mutations): void
	{
		$column = \str_replace('.', '_', $property); // cannot bind "."
		
		if (\is_array($rawValue)) {
			foreach ($rawValue as $language => $value) {
				if (!\in_array($language, $mutations)) {
					throw new \InvalidArgumentException("Language $language is not in available languages");
				}
				
				$realProperty = $column . Connection::MUTATION_SEPARATOR . $language;
				$values["$varPrefix$realProperty$varPostfix"] = \is_bool($value) ? (int) $value : $value;
				$binds[":$varPrefix$realProperty$varPostfix"] = $realProperty;
			}
			
			return;
		}
		
		if (\is_scalar($rawValue) || $rawValue === null) {
			$values["$varPrefix$column$varPostfix"] = \is_bool($rawValue) ? (int) $rawValue : $rawValue;
			$binds[":$varPrefix$column$varPostfix"] = "$property";
			
			return;
		}
		
		if ($rawValue instanceof Literal) {
			$binds[(string) $rawValue] = $property;
			
			return;
		}
		
		if ($rawValue instanceof ICollection) {
			$binds[(string)$rawValue] = $property;
			$values += $rawValue->getVars();
			
			return;
		}
		
		$type = \is_object($rawValue) ? \get_class($rawValue) : \gettype($rawValue);
		
		throw new InvalidStateException(InvalidStateException::INVALID_BINDER_VAR, "$property of $type");
	}
}
