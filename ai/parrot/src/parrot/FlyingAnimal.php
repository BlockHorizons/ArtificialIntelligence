<?php

declare(strict_types = 1);

namespace parrot;

use pocketmine\entity\Creature;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;

abstract class FlyingAnimal extends Creature {

	/**
	 * @return bool
	 */
	public function isInAir(): bool {
		return !$this->isOnGround();
	}

	/**
	 * @param Player $player
	 */
	public function spawnTo(Player $player) {
		$pk = new AddEntityPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = static::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}
}