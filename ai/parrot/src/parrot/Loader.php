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
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase implements Listener {

	public function onEnable() {
		Entity::registerEntity(Parrot::class, true);
		Item::addCreativeItem(Item::get(Item::SPAWN_EGG, 30));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 */
	public function interactionHandler(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if($packet instanceof InteractPacket) {
			$player = $event->getPlayer();
			$entity = $event->getPlayer()->getLevel()->getEntity($packet->target);
			if($packet->action === $packet::ACTION_MOUSEOVER) {
				if($entity instanceof Feedable && $entity->getFeedableComponent()->canBeFedWith($player->getInventory()->getItemInHand())) {
					$entity->getFeedableComponent()->toggleFeedButton($player);
				} elseif($entity instanceof Tamable && $entity->getTamableComponent()->canBeTamedWith($player->getInventory()->getItemInHand())) {
					$entity->getTamableComponent()->toggleTameButton($player);
				}
			} elseif($packet->action === $packet::ACTION_RIGHT_CLICK) {
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
				}
			}
		}
	}
}