<?php

declare(strict_types=1);

namespace StORM;

/**
 * Class Model
 * @deprecated Use class Entity instead of Model
 * @package StORM
 */
abstract class Model extends Entity
{
	private const FOREIGN_KEY_PREFIX = 'fk_';
	
	/**
	 * @deprecated Use class Entity instead of Model
	 * @param string $property
	 * @param string|null $mutation
	 * @return mixed|string
	 */
	public function getValue(string $property, ?string $mutation = null)
	{
		if ($this->getStructure()->hasRelation($property)) {
			$property = self::FOREIGN_KEY_PREFIX . $property;
		}
		
		return parent::getValue($property, $mutation);
	}
}
