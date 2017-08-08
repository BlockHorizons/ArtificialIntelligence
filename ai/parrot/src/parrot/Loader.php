<?php

declare(strict_types = 1);

namespace parrot;

use parrot\interfaces\Feedable;
use parrot\interfaces\Tamable;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase implements Listener {

	public function onEnable() {
		Entity::registerEntity(Parrot::class, true);
		Item::addCreativeItem(Item::get(Item::SPAWN_EGG, 30));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 *
	 * @priority LOW
	 */
	public function interactionHandler(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if($packet instanceof InteractPacket) {
			$player = $event->getPlayer();
			$entity = $event->getPlayer()->getLevel()->getEntity($packet->target);
			if($packet->action === $packet::ACTION_MOUSEOVER) {
				if($entity instanceof Feedable && $entity->getFeedableComponent()->canBeFedWith($player->getInventory()->getItemInHand())) {
					$entity->getFeedableComponent()->showFeedButton($player);
				} elseif($entity instanceof Tamable && $entity->getTamableComponent()->canBeTamedWith($player->getInventory()->getItemInHand())) {
					$entity->getTamableComponent()->showTameButton($player);
				} elseif($entity instanceof Tamable && $entity->getTamableComponent()->hasValidUUID()) {
					$entity->getTamableComponent()->showSitStandButton($player);
				} else {
					$player->setDataProperty(Entity::DATA_INTERACTIVE_TAG, Entity::DATA_TYPE_STRING, "");
				}
			}
		} elseif($packet instanceof InventoryTransactionPacket) {
			if($packet->transactionData->transactionType === $packet::TYPE_USE_ITEM_ON_ENTITY) {
				if($packet->transactionData->actionType === $packet::USE_ITEM_ON_ENTITY_ACTION_INTERACT) {
					$player = $event->getPlayer();
					$entity = $player->getLevel()->getEntity($packet->transactionData->entityRuntimeId);
					$tag = $player->getDataProperty(Entity::DATA_INTERACTIVE_TAG);
					switch($tag) {
						case "Feed":
							if($entity instanceof Feedable) {
								$entity->getFeedableComponent()->feed($player);
							}
							break;
						case "Tame":
							if($entity instanceof Tamable) {
								$entity->getTamableComponent()->tame($player);
							}
							break;
						case "Sit":
							if($entity instanceof Tamable) {
								$entity->setSitting();
							}
							break;
						case "Stand":
							if($entity instanceof Tamable) {
								$entity->setSitting(false);
							}
							break;
					}
				}
			}
		}
	}
}