<?php

declare(strict_types = 1);

namespace parrot\components;

use parrot\interfaces\Feedable;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\Player;

class FeedableComponent extends EntityComponent {

	/**
	 * @param Player $feeder
	 *
	 * @return bool
	 */
	public function feed(Player $feeder): bool {
		/** @var Feedable|Entity $entity */
		$entity = $this->getEntity();
		if(!$this->canBeFedWith($feeder->getInventory()->getItemInHand())) {
			return false;
		}
		$entity->onFeed();
		return true;
	}

	/**
	 * @param Player $player
	 * @param bool   $value
	 */
	public function showFeedButton(Player $player, bool $value = true) {
		if($value) {
			$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "Feed");
		} else {
			$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "");
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function hasFeedButton(Player $player): bool {
		return $player->getDataProperty(Entity::DATA_INTERACTIVE_TAG) === "Feed";
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function toggleFeedButton(Player $player): bool {
		$this->showFeedButton($player, !($value = $this->hasFeedButton($player)));
		return $value;
	}

	/**
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function canBeFedWith(Item $item): bool {
		/** @var Entity|Feedable $entity */
		$entity = $this->getEntity();
		return $item->getId() === $entity->getFeedingItem()->getId() and $item->getDamage() === $entity->getFeedingItem()->getDamage() and $item->getCount() >= $entity->getFeedingItem()->getCount();
	}
}