<?php

declare(strict_types = 1);

namespace StORM;

/**
 * @template T of \StORM\Entity
 */
interface IEntityParent
{
	/**
	 * @return \StORM\Repository<T>
	 */
	public function getRepository(): Repository;
}
