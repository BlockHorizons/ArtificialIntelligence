<?php

declare(strict_types = 1);

namespace parrot\interfaces;

use parrot\components\TamableComponent;
use pocketmine\item\Item;
use pocketmine\metadata\Metadatable;

interface Tamable extends Metadatable {

	/**
	 * @return Item
	 */
	public function getTamingItem(): Item;

	/**
	 * @return TamableComponent
	 */
	public function getTamableComponent(): TamableComponent;

	/**
	 * @return bool
	 */
	public function isSitting(): bool;

	/**
	 * @param bool $value
	 */
	public function setSitting(bool $value = true);
}