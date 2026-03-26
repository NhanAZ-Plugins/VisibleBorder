<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;

class WorldBorderEntity extends Entity {
	public const IDENTIFIER = "visibleborder:world_border";

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setHasGravity(false);
	}

	public static function getNetworkTypeId() : string{
		return self::IDENTIFIER;
	}

	public function canSaveWithChunk() : bool{
		return false;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		// paper-thin visuals; size doesn't affect render scale we set via metadata
		return new EntitySizeInfo(0.1, 0.1, 0.1);
	}

	public function isInvisible() : bool{
		return true; // hide hitbox + name tag client-side
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function canBeCollidedWith() : bool{
		return false;
	}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}
}
