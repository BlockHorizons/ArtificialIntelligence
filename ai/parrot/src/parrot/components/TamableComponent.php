<?php

declare(strict_types = 1);

namespace parrot\components;

use parrot\interfaces\Tamable;
use parrot\Parrot;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\Player;
use pocketmine\utils\UUID;

class TamableComponent extends EntityComponent {

	/** @var UUID */
	private $ownerUUID = null;

	/**
	 * @param Tamable|Entity $entity
	 */
	public function __construct(Tamable $entity) {
		parent::__construct($entity);
		if(isset($entity->namedtag->OwnerUUID)) {
			$this->ownerUUID = UUID::fromString($entity->namedtag->OwnerUUID->getValue());
		}
	}

	/**
	 * @return bool
	 */
	public function hasValidUUID(): bool {
		return $this->ownerUUID !== null;
	}

	/**
	 * @return UUID
	 */
	public function getTamerUUID(): UUID {
		return $this->ownerUUID;
	}

	/**
	 * @param Player $player
	 */
	public function setOwningPlayer(Player $player) {
		$this->getEntity()->setOwningEntity($player);
		$this->getEntity()->namedtag->OwnerUUID = new StringTag("OwnerUUID", $player->getUniqueId()->toString());
		$this->ownerUUID = $player->getUniqueId();
	}

	/**
	 * @param Player $tamer
	 *
	 * @return bool
	 */
	public function tame(Player $tamer): bool {
		if(!$this->canBeTamedWith($tamer->getInventory()->getItemInHand()) or $this->hasValidUUID()) {
			return false;
		}
		$packet = new EntityEventPacket();
		$packet->entityRuntimeId = $this->getEntity()->getId();
		if(random_int(0, 3) === 3) {
			$packet->event = EntityEventPacket::TAME_SUCCESS;
			$this->setOwningPlayer($tamer);
			$this->getEntity()->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_TAMED, true);
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
		return $item->getId() === $entity->getTamingItem()->getId() and $item->getDamage() === $entity->getTamingItem()->getDamage() and $item->getCount() >= $entity->getTamingItem()->getCount();
	}

	/**
	 * @param Player $player
	 * @param bool   $value
	 */
	public function showTameButton(Player $player, bool $value = true) {
		if($value and !$this->hasValidUUID()) {
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


	/**
	 * @param Player $player
	 * @param bool   $value
	 */
	public function showSitStandButton(Player $player, bool $value = true) {
		/** @var Parrot $entity */
		$entity = $this->getEntity();
		if($this->hasValidUUID()) {
			if(!$value) {
				$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "");
			} elseif($entity->isSitting()) {
				$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "Stand");
			} else {
				$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "Sit");
			}
		}

	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasSitStandButton(Player $player): bool {
		return $player->getDataProperty(Entity::DATA_INTERACTIVE_TAG) === "Tame";
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function toggleSitStandButton(Player $player): bool {
		$this->showTameButton($player, !($value = $this->hasTameButton($player)));
		return $value;
	}
}