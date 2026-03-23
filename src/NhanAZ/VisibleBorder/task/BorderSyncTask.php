<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\task;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\ZoneRuleManager;
use pocketmine\scheduler\Task;
use pocketmine\Server;

final class BorderSyncTask extends Task {
	public function __construct(
		private BorderManager $manager,
		private ZoneRuleManager $rules
	){
	}

	public function onRun() : void{
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			$this->manager->syncPlayerBorders($player);
			$this->manager->tickPlayer($player);
			$this->rules->tickPlayer($player);
		}
	}
}
