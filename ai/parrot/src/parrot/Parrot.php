<?php

declare(strict_types = 1);

namespace parrot;

use parrot\components\FeedableComponent;
use parrot\components\ShoulderSittingComponent;
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
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\Player;

class Parrot extends FlyingAnimal implements Tamable, Feedable {

	const NETWORK_ID = 30;

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
	/** @var ShoulderSittingComponent */
	private $shoulderSittingComponent = null;
	/** @var bool */
	public $riding = false;

	public function initEntity() {
		parent::initEntity();
		if(!isset($this->namedtag->Variant)) {
			$variant = random_int(0, 4);
			$this->setDataProperty(self::DATA_VARIANT, self::DATA_TYPE_INT, $variant);
			$this->namedtag->Variant = new IntTag("Variant", $variant);
		} else {
			$this->setDataProperty(self::DATA_VARIANT, self::DATA_TYPE_INT, $this->namedtag->Variant->getValue());
		}
		if(!isset($this->namedtag->Sitting)) {
			$this->namedtag->Sitting = new ByteTag("Sitting", 0);
		} else {
			$this->setSitting((bool) $this->namedtag->Sitting->getValue());
		}
		$this->setMaxHealth(6);
		$this->setHealth(6);
		$this->directionFindTick = mt_rand(30, 80);

		$this->feedableComponent = new FeedableComponent($this);
		$this->tamableComponent = new TamableComponent($this);
		$this->shoulderSittingComponent = new ShoulderSittingComponent($this);
		if($this->getTamableComponent()->hasValidUUID()) {
			$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_TAMED, true);
		}
	}

	public function saveNBT() {
		parent::saveNBT();
		$this->namedtag->Sitting->setValue((int) $this->isSitting());
	}

	/**
	 * @param float             $damage
	 * @param EntityDamageEvent $source
	 */
	public function attack($damage, EntityDamageEvent $source) {
		if($source->getCause() === $source::CAUSE_FALL or $this->riding) {
			$source->setCancelled();
		}
		parent::attack($damage, $source);
		if($source->isCancelled()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$source->setKnockback(0);
			$this->motionX = $this->motionZ = 0;
			$this->generateNewDirection();
			$this->setFlying();
			if($this->isSitting()) {
				$this->setSitting(false);
			}
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
			if($this->getTamableComponent()->hasValidUUID()) {
				foreach($this->getLevel()->getServer()->getOnlinePlayers() as $player) {
					if($player->getUniqueId()->equals($this->getTamableComponent()->getTamerUUID())) {
						$this->getTamableComponent()->setOwningPlayer($player);
					}
				}
				if($this->getOwningEntity() !== null) {
					if(!$this->observedEntity instanceof Player) {
						$this->observedEntity = $this->getOwningEntity();
					}
					if($this->distance($this->observedEntity) > 16 and !$this->isSitting() and $this->observedEntity->isOnGround()) {
						$this->teleportToOwner();
					} elseif($this->distance($this->observedEntity) <= 1.2 and !$this->isSitting()) {
						$this->shoulderSittingComponent->sitOnShoulder($this->observedEntity);
					}
				}
			}
			if($this->isInsideOfWater()) {
				$this->motionY += $this->gravity / 8 * $tickDiff;
			} elseif($this->isInAir() and !$this->isFlying()) {
				$this->motionY -= $this->gravity / 8 * $tickDiff;
			}

			if($this->isElevating()) {

				$this->motionY += $this->gravity * $this->drag * 1.1 * $tickDiff;
				if($this->elevatingTicks > 40) {
					$this->elevating = false;
				}
				$this->elevatingTicks += $tickDiff;

			} elseif($this->isFlying()) {

				if($this->destination === null) {
					$this->setFlying(false);
					return true;
				}
				if($this->flyingTicks < mt_rand(2, 4)) {
					$this->motionY -= $this->motionY * $this->drag * 4 * $tickDiff;
				}
				list($x, $y, $z) = $this->subtractVector3($this->destination, ($this->isObserving() ? true : false));
				if($y < 0 and $this->isObserving()) {
					$this->motionY = $y * $this->drag * $tickDiff;
				} elseif($this->flyingTicks >= 100) {
					$this->motionY = -$this->gravity / 2 * $tickDiff;
					$this->motionX /= 3;
					$this->motionZ /= 3;
				} elseif(!$this->isCollidedHorizontally) {
					$this->motionY = -$this->drag * 0.5;
				} else {
					if($this->flyingTicks < 30) {
						$this->motionY += $this->drag * 4;
					} else {
						$this->motionY -= $this->drag * 2;
						$this->setFlying(false);
						return true;
					}
				}
				$this->motionX = 0.2 * ($x / $this->distance($this->destination)) * $tickDiff;
				$this->motionZ = 0.2 * ($z / $this->distance($this->destination)) * $tickDiff;
				$this->calculateYawPitch($x, $y, $z);
				$this->pitch = 0;

				if($this->isOnGround() or sqrt($x * $x + $z * $z) <= 0.3) {
					$this->setFlying(false);
					$this->motionX = $this->motionZ = 0;
					$this->motionY -= $this->gravity * 2 * $tickDiff;
				}
				$this->flyingTicks += $tickDiff;

			} elseif(!$this->isObserving()) {

				$this->directionFindTick -= $tickDiff;
				if($this->directionFindTick <= 0) {
					$this->generateNewDirection();
					$this->setFlying();
				}
				if(mt_rand(1, 50) === 50) {
					$this->facingMode = ((bool) $this->facingMode ? self::FACING_MODE_IDLE : self::FACING_MODE_OBSERVE);
					if($this->facingMode === self::FACING_MODE_IDLE) {
						$this->yaw = $this->yaw + mt_rand(-30, 30);
						$this->pitch = 0;
					}
				}
				if($this->facingMode === self::FACING_MODE_OBSERVE) {
					$nearbyParrots = [];
					$target = null;
					$facingModified = false;
					foreach($this->getEntitiesWithinDistance(16) as $entity) {
						if($this->distance($entity) <= 8 and !$facingModified) {
							if($entity instanceof Player) {
								list($x, $y, $z) = $this->subtractVector3(new Vector3($entity->x, $entity->y + $entity->getEyeHeight(), $entity->z), true);
								$this->calculateYawPitch($x, $y, $z);
								$facingModified = true;
								continue;
							}
						}
						if($entity instanceof Player or !$entity instanceof Living) {
							continue;
						}
						if($entity instanceof Parrot) {
							$nearbyParrots[] = $entity;
							continue;
						}
						if($target === null) {
							$target = $entity;
						}
					}
					if($target !== null) {
						foreach($nearbyParrots as $parrot) {
							$parrot->observe($target);
						}
					}
				}
			} else {
				list($x, $y, $z) = $this->subtractVector3($this->observedEntity, true);
				$this->calculateYawPitch($x, $y, $z);
				if(($distance = $this->distance($this->observedEntity)) > 4 or $distance <= 1) {
					$this->flyTowards($this->observedEntity->asVector3());
				}
			}

			if($this->isObserving()) {
				if(!$this->observedEntity->isAlive() or $this->observedEntity->closed) {
					$this->observedEntity = null;
				}
			}
			if($this->isSitting()) {
				$this->motionX = $this->motionZ = 0;
				if(!$this->isOnGround()) {
					$this->motionY = -$this->gravity / 2;
				}
				if($this->riding and $this->getOwningEntity() !== null) {
					$this->yaw = $this->getOwningEntity()->yaw;
					$this->pitch = $this->getOwningEntity()->pitch;
				} elseif($this->riding) {
					$this->riding = false;
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
	 * @param Vector3 $vector3
	 * @param bool    $backwards
	 *
	 * @return array
	 */
	private function subtractVector3(Vector3 $vector3, bool $backwards = false): array {
		if($backwards) {
			return [
				$vector3->x - $this->x,
				$vector3->y - $this->y,
				$vector3->z - $this->z
			];
		}
		return [
			$this->x - $vector3->x,
			$this->y - $vector3->y,
			$this->z - $vector3->z
		];
	}

	/**
	 * @param float $x
	 * @param float $y
	 * @param float $z
	 */
	public function calculateYawPitch(float $x, float $y, float $z) {
		$this->yaw = rad2deg(atan2(-$x, $z));
		$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
	}

	/**
	 * @param bool $value
	 */
	private function setFlying(bool $value = true) {
		if(!$value) {
			$this->destination = null;
		}
		$this->flyingTicks = 0;
		$this->isFlying = $value;
		$this->elevating = $value;
		$this->elevatingTicks = 0;
		$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_RIDING, $value);
	}

	/**
	 * @param Vector3 $vector3
	 */
	public function flyTowards(Vector3 $vector3) {
		$this->setFlying();
		$degree = mt_rand(1, 360);

		$xOffset = lcg_value() * 3;
		$zOffset = lcg_value() * 3;
		$x = (cos(deg2rad($degree)) * (2 + $xOffset)) + $vector3->x;
		$z = (sin(deg2rad($degree)) * (2 + $zOffset)) + $vector3->z;
		$this->destination = new Vector3($x, $vector3->y, $z);
	}

	/**
	 * @return bool
	 */
	public function teleportToOwner(): bool {
		if($this->getOwningEntity() === null) {
			return false;
		}
		$vector3 = $this->getOwningEntity()->asVector3();
		$degree = mt_rand(1, 360);
		$x = (cos(deg2rad($degree))) * 2 + $vector3->x;
		$z = (sin(deg2rad($degree))) * 2 + $vector3->z;
		$this->teleport(new Vector3($x, $vector3->y + 1, $z));
		return true;
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
			$this->directionFindTick = mt_rand(10, 30);
		}
		$flyDistance = 12;
		$x = random_int(-12, 12);
		$z = ($flyDistance - abs($x)) * (random_int(0, 1) === 1 ? 1 : -1);
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

	/**
	 * @return bool
	 */
	public function isSitting(): bool {
		return $this->getDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SITTING);
	}

	/**
	 * @param bool $value
	 */
	public function setSitting(bool $value = true) {
		$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SITTING, $value);
	}

	/**
	 * @return ShoulderSittingComponent
	 */
	public function getShoulderSittingComponent(): ShoulderSittingComponent {
		return $this->shoulderSittingComponent;
	}
}