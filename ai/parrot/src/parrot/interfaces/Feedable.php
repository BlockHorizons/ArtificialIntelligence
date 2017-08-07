<?php

declare(strict_types = 1);

namespace parrot\interfaces;

use parrot\components\FeedableComponent;
use pocketmine\item\Item;
use pocketmine\metadata\Metadatable;

interface Feedable extends Metadatable {

	/**
	 * @return Item
	 */
	public function getFeedingItem(): Item;

	/**
	 * @return bool
	 */
	public function onFeed(): bool;

	/**
	 * @return FeedableComponent
	 */
	public function getFeedableComponent(): FeedableComponent;
}