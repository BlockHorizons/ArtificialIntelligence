<?php

declare(strict_types = 1);

namespace parrot;

use pocketmine\entity\Creature;

abstract class FlyingAnimal extends Creature {

	/**
	 * @return bool
	 */
	public function isInAir(): bool {
		return !$this->isOnGround();
	}
}