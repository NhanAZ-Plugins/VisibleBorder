<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\listener;

use NhanAZ\VisibleBorder\BorderManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

final class PlayerMoveListener implements Listener {
	public function __construct(private BorderManager $manager){
	}

	public function onMove(PlayerMoveEvent $event) : void{
		$this->manager->handleMove($event);
	}
}
