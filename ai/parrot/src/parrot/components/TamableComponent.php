<?php

declare(strict_types = 1);

namespace parrot\components;

use parrot\interfaces\Tamable;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\Player;

class TamableComponent extends EntityComponent {

	private $ownerUUID = 0;

	public function __construct(Tamable $entity) {
		parent::__construct($entity);
	}

	/**
	 * @param Player $player
	 */
	public function setOwningPlayer(Player $player) {
		$this->ownerUUID = $player->getUniqueId();
	}

	/**
	 * @param Player $tamer
	 *
	 * @return bool
	 */
	public function tame(Player $tamer): bool {
		if(!$this->canBeTamedWith($tamer->getInventory()->getItemInHand())) {
			return false;
		}
		$packet = new EntityEventPacket();
		$packet->entityRuntimeId = $this->getEntity()->getId();
		if(mt_rand(0, 3) === 3) {
			$packet->event = EntityEventPacket::TAME_SUCCESS;
			$return = true;
		} else {
			$packet->event = EntityEventPacket::TAME_FAIL;
			$return = false;
		}
		foreach($this->getEntity()->getLevel()->getPlayers() as $player) {
			$player->dataPacket($packet);
		}
		return $return;
	}

	/**
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function canBeTamedWith(Item $item): bool {
		/** @var Entity|Tamable $entity */
		$entity = $this->getEntity();
		return $item->getId() === $entity->getTamingItem()->getId() && $item->getDamage() === $entity->getTamingItem()->getDamage() && $item->getCount() <= $entity->getTamingItem()->getCount();
	}

	/**
	 * @param Player $player
	 * @param bool   $value
	 */
	public function showTameButton(Player $player, bool $value = true) {
		if($value) {
			$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "Tame");
		} else {
			$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "");
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasTameButton(Player $player): bool {
		return $player->getDataProperty(Entity::DATA_INTERACTIVE_TAG) === "Tame";
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function toggleTameButton(Player $player): bool {
		$this->showTameButton($player, !($value = $this->hasTameButton($player)));
		return $value;
	}
}