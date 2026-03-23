<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\task;

use NhanAZ\VisibleBorder\BorderManager;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class BorderSyncTask extends Task {
	public function __construct(private BorderManager $manager){
	}

	public function onRun() : void{
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			if(!$this->manager->hasBorder($player)){
				$this->manager->spawnBorder($player);
			}
		}
	}
}
