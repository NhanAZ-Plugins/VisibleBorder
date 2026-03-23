<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\event;

use pocketmine\event\Cancellable;
use pocketmine\event\Event;

class BorderShrinkEvent extends Event implements Cancellable {
	use \pocketmine\event\CancellableTrait;

	public function __construct(private string $worldName, private float $newRadius){
	}

	public function getWorldName() : string{
		return $this->worldName;
	}

	public function getNewRadius() : float{
		return $this->newRadius;
	}
}
