<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\event;

use pocketmine\event\Event;
use pocketmine\player\Player;

class BorderLeaveEvent extends Event {
	public function __construct(private Player $player){
	}

	public function getPlayer() : Player{
		return $this->player;
	}
}
