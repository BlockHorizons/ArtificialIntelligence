<?php

namespace parrot\components;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket as Sound;

class SoundImitatorComponent extends EntityComponent {

	const SOUNDS = [
		14 => Sound::SOUND_IMITATE_WOLF,
		28 => Sound::SOUND_IMITATE_POLAR_BEAR,
		32 => Sound::SOUND_IMITATE_ZOMBIE,
		33 => Sound::SOUND_IMITATE_CREEPER,
		34 => Sound::SOUND_IMITATE_SKELETON,
		35 => Sound::SOUND_IMITATE_SPIDER,
		36 => Sound::SOUND_IMITATE_ZOMBIE_PIGMAN,
		37 => Sound::SOUND_IMITATE_SLIME,
		38 => Sound::SOUND_IMITATE_ENDERMAN,
		39 => Sound::SOUND_IMITATE_SILVERFISH,
		40 => Sound::SOUND_IMITATE_CAVE_SPIDER,
		41 => Sound::SOUND_IMITATE_GHAST,
		42 => Sound::SOUND_IMITATE_MAGMA_CUBE,
		44 => Sound::SOUND_IMITATE_ZOMBIE_VILLAGER,
		45 => Sound::SOUND_IMITATE_WITCH,
		46 => Sound::SOUND_IMITATE_STRAY,
		47 => Sound::SOUND_IMITATE_HUSK,
		48 => Sound::SOUND_IMITATE_WITHER_SKELETON,
		50 => Sound::SOUND_IMITATE_ELDER_GUARDIAN,
		52 => Sound::SOUND_IMITATE_WITHER,
		53 => Sound::SOUND_IMITATE_ENDER_DRAGON,
		54 => Sound::SOUND_IMITATE_SHULKER,
		57 => Sound::SOUND_IMITATE_VINDICATION_ILLAGER,
		104 => Sound::SOUND_IMITATE_EVOCATION_ILLAGER,
		105 => Sound::SOUND_IMITATE_VEX,
		106 => Sound::SOUND_IMITATE_ILLUSION_ILLAGER,
	];

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function createSoundFor(Entity $entity): bool {
		if(!isset(self::SOUNDS[$entity::NETWORK_ID])) {
			return false;
		}
		$sound = self::SOUNDS[$entity::NETWORK_ID];
		$packet = new LevelSoundEventPacket();
		$packet->sound = $sound;
		list($packet->x, $packet->y, $packet->z) = [$this->getEntity()->x, $this->getEntity()->y, $this->getEntity()->z];
		foreach($entity->getLevel()->getPlayers() as $player) {
			$player->datapacket($packet);
		}
		return true;
	}
}