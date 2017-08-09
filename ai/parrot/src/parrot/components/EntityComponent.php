<?php

declare(strict_types = 1);

namespace parrot\components;

use pocketmine\entity\Entity;

abstract class EntityComponent {

	/** @var Entity */
	protected $entity = null;

	public function __construct(Entity $entity) {
		$this->entity = $entity;
	}

	/**
	 * @return Entity
	 */
	public function getEntity(): Entity {
		return $this->entity;
	}
}