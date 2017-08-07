<?php

declare(strict_types = 1);

namespace parrot;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase {

	public function onEnable() {
		Entity::registerEntity(Parrot::class, true);
		Item::addCreativeItem(Item::get(Item::SPAWN_EGG, 30));
	}
}