<?php

declare(strict_types = 1);

namespace parrot\components;

use parrot\Parrot;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;
use pocketmine\Player;

class ShoulderSittingComponent extends EntityComponent {

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasShoulderSpace(Player $player): bool {
		if(!isset($player->namedtag->LeftShoulderParrot) or !isset($player->namedtag->RightShoulderParrot)) {
			$player->namedtag->LeftShoulderParrot = new IntTag("LeftShoulder", 0);
			$player->namedtag->RightShoulderParrot = new IntTag("RightShoulder", 0);
			return true;
		}
		if($this->hasLeftShoulderSpace($player) or $this->hasRightShoulderSpace($player)) {
			return true;
		}
		return false;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasLeftShoulderSpace(Player $player): bool {
		if($player->namedtag->LeftShoulderParrot->getValue() === 0) {
			return true;
		}
		return false;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasRightShoulderSpace(Player $player): bool {
		if($player->namedtag->RightShoulderParrot->getValue() === 0) {
			return true;
		}
		return false;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	private function occupyShoulder(Player $player): bool {
		/** @var Parrot $parrot */
		$parrot = $this->getEntity();
		if(!$parrot->getTamableComponent()->hasValidUUID()) {
			return false;
		}
		if(!$this->hasShoulderSpace($player)) {
			return false;
		}
		if($this->hasLeftShoulderSpace($player)) {
			$player->namedtag->LeftShoulderParrot->setValue($parrot->getId());
			$parrot->setDataProperty(Entity::DATA_RIDER_SEAT_POSITION, Entity::DATA_TYPE_VECTOR3F, [-0.425, -0.15, 0]);
		} else {
			$player->namedtag->RightShoulderParrot->setValue($parrot->getId());
			$parrot->setDataProperty(Entity::DATA_RIDER_SEAT_POSITION, Entity::DATA_TYPE_VECTOR3F, [0.425, -0.15, 0]);
		}
		return true;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function sitOnShoulder(Player $player): bool {
		/** @var Parrot $parrot */
		$parrot = $this->getEntity();
		if(!($return = $this->occupyShoulder($player))) {
			return false;
		}
		$parrot->setSitting();

		$packet = new SetEntityLinkPacket();
		$packet->link = [
			$player->getId(),
			$parrot->getId(),
			1,
			2
		];
		$parrot->riding = true;
		$parrot->getLevel()->getServer()->broadcastPacket($parrot->getLevel()->getServer()->getOnlinePlayers(), $packet);
		return true;
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function dumpParrots(Player $player): bool {
		$this->hasShoulderSpace($player);
		if($this->hasLeftShoulderSpace($player) and $this->hasRightShoulderSpace($player)) {
			return false;
		}
		if(!$this->hasLeftShoulderSpace($player)) {
			$packet = new SetEntityLinkPacket();
			$idLeft = $player->namedtag->LeftShoulderParrot->getValue();
			$parrots[] = $idLeft;
			$packet->link = [
				$player->getId(),
				$idLeft,
				0,
				2
			];
			$player->getLevel()->getServer()->broadcastPacket($player->getLevel()->getServer()->getOnlinePlayers(), $packet);
			if(!$this->hasRightShoulderSpace($player)) {
				$packet->link[1] = ($idRight = $player->namedtag->RightShoulderParrot->getValue());
				$player->getLevel()->getServer()->broadcastPacket($player->getLevel()->getServer()->getOnlinePlayers(), $packet);
				$parrots[] = $idRight;
			}
			foreach($parrots as $parrotId) {
				/** @var Parrot $parrot */
				$parrot = $player->getLevel()->getEntity($parrotId);
				$parrot->setSitting(false);
				$parrot->riding = false;
				$parrot->teleportToOwner();
			}
			$player->namedtag->LeftShoulderParrot->setValue(0);
			$player->namedtag->RightShoulderParrot->setValue(0);

			return true;
		}
		$player->namedtag->LeftShoulderParrot->setValue(0);
		$player->namedtag->RightShoulderParrot->setValue(0);

		return false;
	}
}