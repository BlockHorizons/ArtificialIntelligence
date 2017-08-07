<?php

declare(strict_types = 1);

namespace parrot\components;

use pocketmine\entity\Entity;
use pocketmine\metadata\Metadatable;

abstract class EntityComponent {

	/** @var Entity */
	protected $entity = null;

	public function __construct(Metadatable $entity) {
		$this->entity = $entity;
	}

	/**
	 * @return Entity
	 */
	public function getEntity(): Entity {
		return $this->entity;
	}
}