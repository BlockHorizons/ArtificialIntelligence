<?php

declare(strict_types = 1);

namespace parrot;

use parrot\components\FeedableComponent;
use parrot\components\TamableComponent;
use parrot\interfaces\Feedable;
use parrot\interfaces\Tamable;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;

class Parrot extends FlyingAnimal implements Tamable, Feedable {

	const NETWORK_ID = 10; // Change this to parrot network ID (30) later in 1.2.

	const FACING_MODE_IDLE = 0;
	const FACING_MODE_OBSERVE = 1;

	/** @var float */
	public $height = 0.9;
	/** @var float */
	public $width = 0.5;
	/** @var float */
	public $length = 0.5;
	/** @var int */
	private $directionFindTick = 0;
	/** @var null|Living */
	private $observedEntity = null;
	/** @var bool */
	private $lookingAtPlayer = false;
	/** @var int */
	private $facingMode = 0;
	/** @var bool */
	private $elevating = false;
	/** @var int */
	private $elevatingTicks = 0;
	/** @var int */
	private $flyingTicks = 0;
	/** @var bool */
	private $isFlying = false;
	/** @var null|Vector3 */
	private $destination = null;
	/** @var FeedableComponent */
	private $feedableComponent = null;
	/** @var TamableComponent */
	private $tamableComponent = null;

	public function initEntity() {
		parent::initEntity();
		$this->setMaxHealth(6);
		$this->setHealth(6);
		$this->directionFindTick = mt_rand(50, 100);

		$this->feedableComponent = new FeedableComponent($this);
		$this->tamableComponent = new TamableComponent($this);
	}

	public function attack($damage, EntityDamageEvent $source) {
		if($source->getCause() === $source::CAUSE_FALL) {
			$source->setCancelled();
		}
		parent::attack($damage, $source);
		if($source->isCancelled()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$source->setKnockback(0);
			$this->motionX = $this->motionZ = 0;
			$this->motionY = $this->gravity * $this->drag * 4;
			$this->generateNewDirection();
			$this->setFlying();
		}
	}

	/**
	 * @param int $tickDiff
	 *
	 * @return bool
	 */
	public function entityBaseTick($tickDiff = 1) {
		if($this->closed) {
			return false;
		}
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isAlive()) {
			if($this->isInAir() && !($this->isFlying())) {
				if($this->isInsideOfWater()) {
					$this->motionY += $this->gravity / 4 * $tickDiff;
				} else {
					$this->motionY -= $this->gravity / 8 * $tickDiff;
				}
			} elseif($this->isElevating()) {
				$this->motionY += $this->gravity * $this->drag * 1.1 * $tickDiff;
				$this->elevatingTicks += $tickDiff;
				if($this->elevatingTicks > 40) {
					$this->elevating = false;
				}
			} elseif($this->isFlying()) {
				if($this->destination === null) {
					$this->setFlying(false);
				}
				if($this->flyingTicks < 4) {
					$this->motionY -= $this->gravity * $this->drag * 4 * $tickDiff;
				}
				$this->flyingTicks += $tickDiff;
				$x = $this->x - $this->destination->x;
				$y = $this->y - $this->destination->y;
				$z = $this->z - $this->destination->z;

				if($this->isObserving()) {
					$x = $this->destination->x - $this->x;
					$y = $this->destination->y - $this->y;
					$z = $this->destination->z - $this->z;
				}
				$distance = $this->distance($this->destination);
				$distanceFlat = sqrt($x * $x + $z * $z);

				if(!$this->isObserving()) {
					$this->motionY -= $this->gravity * $this->drag * 2 * $tickDiff;
				} elseif($y > 0) {
					$this->motionY = $y * 0.1 * $tickDiff;
				} else {
					$this->motionY -= $this->gravity * $this->drag * 2 * $tickDiff;
				}
				$this->motionX = 0.2 * ($x / $distance) * $tickDiff;
				$this->motionZ = 0.2 * ($z / $distance) * $tickDiff;

				$this->yaw = rad2deg(atan2(-$x, $z));
				$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));

				if($this->isOnGround() || $distanceFlat <= 0.3) {
					$this->setFlying(false);
					$this->motionX = 0;
					$this->motionZ = 0;
				}
			} else {
				$this->directionFindTick -= $tickDiff;
				$this->destination = null;
				if($this->directionFindTick <= 0 && !$this->isObserving()) {
					$this->generateNewDirection();
					$this->setFlying();
				} else {
					if(!$this->isObserving()) {
						if(mt_rand(1, 60) === 1) {
							$this->facingMode = ($this->lookingAtPlayer ? self::FACING_MODE_IDLE : self::FACING_MODE_OBSERVE);
						}
						$this->pitch = 0;
						$this->yaw = $this->yaw + mt_rand(-30, 30);

						if($this->facingMode === 1) {
							foreach($this->getEntitiesWithinDistance(8) as $entity) {
								if($entity instanceof Player) {
									$x = $entity->x - $this->x;
									$y = $entity->y + $entity->getEyeHeight() - $this->y;
									$z = $entity->z - $this->z;
									$this->yaw = rad2deg(atan2(-$x, $z));
									$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
									break;
								}
							}
						}
					} else {
						$x = $this->observedEntity->x - $this->x;
						$y = $this->observedEntity->y - $this->y;
						$z = $this->observedEntity->z - $this->z;
						$this->yaw = rad2deg(atan2(-$x, $z));
						$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
						if($this->distance($this->observedEntity) > 3) {
							$this->flyTowards($this->observedEntity->asVector3());
						}
					}
				}
			}
			if(!$this->isObserving()) {
				foreach($this->getEntitiesWithinDistance(16) as $entity) {
					if(!$entity instanceof Player && $entity instanceof Living) {
						$this->observe($entity);
						break;
					}
				}
			}

			if($this->observedEntity !== null) {
				if(!$this->observedEntity->isAlive() || $this->observedEntity->closed || $this->distance($this->observedEntity) > 20) {
					$this->observedEntity = null;
				}
			}
			$this->move($this->motionX, $this->motionY, $this->motionZ);
		}
		return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
	}

	/**
	 * @return bool
	 */
	public function isFlying(): bool {
		return $this->isFlying;
	}

	/**
	 * @return bool
	 */
	public function isElevating(): bool {
		return $this->elevating;
	}

	/**
	 * @return bool
	 */
	public function isObserving(): bool {
		return $this->observedEntity !== null;
	}

	/**
	 * @param bool $value
	 */
	private function setFlying(bool $value = true) {
		$this->flyingTicks = 0;
		$this->isFlying = $value;
		$this->elevating = $value;
		$this->elevatingTicks = 0;
	}

	/**
	 * @param Vector3 $vector3
	 */
	public function flyTowards(Vector3 $vector3) {
		$this->setFlying();
		$this->destination = $vector3->add(lcg_value(), 0, lcg_value());
	}

	/**
	 * @param int $distance
	 *
	 * @return Entity[]
	 */
	private function getEntitiesWithinDistance(int $distance): array {
		$BB = new AxisAlignedBB($this->x - $distance, $this->y - $distance, $this->z - $distance, $this->x + $distance, $this->y + $distance, $this->z + $distance);
		return $this->getLevel()->getNearbyEntities($BB, $this);
	}

	/**
	 * @param Living $living
	 *
	 * @return bool
	 */
	public function observe(Living $living): bool {
		if($living instanceof Parrot) {
			return false;
		}
		$this->observedEntity = $living;
		return true;
	}

	/**
	 * @return bool
	 */
	private function generateNewDirection(): bool {
		if($this->directionFindTick > 0) {
			return false;
		}
		$this->directionFindTick = mt_rand(100, 400);
		if($this->isObserving()) {
			$this->directionFindTick = mt_rand(30, 50);
		}
		$flyDistance = 12;
		$x = mt_rand(-12, 12);
		$z = $flyDistance - abs($x);
		$this->destination = new Vector3($this->x + $x, $this->y, $this->z + $z);
		return true;
	}

	/**
	 * @param bool $value
	 */
	public function setDancing(bool $value = true) {
		$this->setDataFlag(self::DATA_FLAGS, 48, $value); // TODO: Replace by constant.
	}

	/**
	 * @return bool
	 */
	public function isDancing(): bool {
		return $this->getDataFlag(self::DATA_FLAGS, 48); // TODO: Replace by constant.
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return "Parrot";
	}

	/**
	 * @param Player $player
	 */
	public function spawnTo(Player $player) {
		$pk = new AddEntityPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->type = self::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);
		parent::spawnTo($player);
	}

	/**
	 * @return array
	 */
	public function getDrops(): array {
		return [
			Item::get(Item::FEATHER, 0, mt_rand(1, 2))
		];
	}

	/**
	 * @return Item
	 */
	public function getTamingItem(): Item {
		return Item::get(Item::SEEDS);
	}

	/**
	 * @return Item
	 */
	public function getFeedingItem(): Item {
		return Item::get(Item::COOKIE);
	}

	/**
	 * @return FeedableComponent
	 */
	public function getFeedableComponent(): FeedableComponent {
		return $this->feedableComponent;
	}

	/**
	 * @return bool
	 */
	public function onFeed(): bool {
		$effect = Effect::getEffect(Effect::WITHER)->setVisible(true)->setAmplifier(1)->setDuration(INT32_MAX);
		$effect->setColor(25, 155, 0);
		$this->addEffect($effect);
		return true;
	}

	/**
	 * @return TamableComponent
	 */
	public function getTamableComponent(): TamableComponent {
		return $this->tamableComponent;
	}
}