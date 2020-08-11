<?php

declare(strict_types=1);

namespace StORM\Meta;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

class Trigger extends AnnotationClass
{
	/**
	 * Action to trigger
	 * @var string
	 */
	protected $manipulation;
	
	/**
	 * When to trigger action
	 * @var string
	 */
	protected $timing;
	
	/**
	 * Definition of the trigger
	 * @var string
	 */
	protected $statement;
	
	public function getManipulation(): string
	{
		return $this->manipulation;
	}
	
	public function setManipulation(string $manipulation): void
	{
		$this->manipulation = $manipulation;
	}

	public function getTiming(): string
	{
		return $this->timing;
	}
	
	public function setTiming(string $timing): void
	{
		$this->timing = $timing;
	}
	
	public function getStatement(): string
	{
		return $this->statement;
	}

	public function setStatement(string $statement): void
	{
		$this->statement = $statement;
	}
	
	public function getSchema(): Schema
	{
		return Expect::structure([
			'name' => Expect::string()->required(),
			'manipulation' => Expect::string()->required(),
			'timing' => Expect::string()->required(),
			'statement' => Expect::string()->required(),
		]);
	}
	
	public static function getAnnotationName(): string
	{
		return 'trigger';
	}
}
