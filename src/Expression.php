<?php

declare(strict_types=1);

namespace StORM;

class Expression
{
	private int $iterator = 0;
	
	/**
	 * @var mixed[]
	 */
	private array $vars = [];
	
	private string $sql = '';
	
	/**
	 * @param string|null $glue
	 * @param string $expression
	 * @param mixed[] $vars
	 * @param string $binderName
	 */
	public function add(?string $glue, string $expression, array $vars = [], string $binderName = '__var'): void
	{
		$iterator = $this->iterator;
		$stmVars = [];
		
		foreach ($vars as $i => $var) {
			$stmVars[":$binderName$i" . "_$iterator"] = $var;
		}
		
		$expression = \vsprintf($expression, \array_keys($stmVars));
		
		if ($this->sql && $glue) {
			$this->sql .= " $glue ";
		}
		
		$this->sql .= "($expression)";
		$this->vars += $stmVars;
		$this->iterator++;
	}

	public function getSql(): string
	{
		return $this->sql;
	}
	
	/**
	 * @return mixed[]
	 */
	public function getVars(): array
	{
		return $this->vars;
	}
}
