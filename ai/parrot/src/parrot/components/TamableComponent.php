<?php

declare(strict_types = 1);

namespace parrot\components;

use pocketmine\Player;

class TamableComponent extends EntityComponent {

	private $ownerUUID = 0;

	/**
	 * @param Player $player
	 */
	public function setOwningPlayer(Player $player) {
		$this->ownerUUID = $player->getUniqueId();
	}

	// TODO
}