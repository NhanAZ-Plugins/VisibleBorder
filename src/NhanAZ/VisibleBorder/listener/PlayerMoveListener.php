<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\listener;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\event\BorderEnterEvent;
use NhanAZ\VisibleBorder\event\BorderLeaveEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

class PlayerMoveListener implements Listener {
	/** @var array<int,bool> */
	private array $outside = [];

	public function __construct(private BorderManager $manager){
	}

	public function onMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		$from = $event->getFrom();
		$to = $event->getTo();
		if($to === null){
			return;
		}
		$cfg = $this->manager->getConfigForWorld($player->getWorld());
		if($cfg === null){
			return;
		}
		$distSq = ($to->x - $cfg["center"][0]) ** 2 + ($to->z - $cfg["center"][1]) ** 2;
		$radius = (float)($cfg["radius"] ?? 0);
		$rSq = $radius * $radius;

		$isInside = $distSq <= $rSq;
		$pid = $player->getId();
		$wasOutside = $this->outside[$pid] ?? false;

		if(!$isInside && !$player->hasPermission("visibleborder.bypass")){
			if($cfg["teleport-back"] ?? false){
				$vec = $to->subtract($cfg["center"][0], 0, $cfg["center"][1])->normalize()->multiply($radius - 0.5);
				$target = $vec->add($cfg["center"][0], $to->y, $cfg["center"][1]);
				$event->cancel();
				$player->teleport($target);
			}else{
				$event->cancel();
			}
			$msg = $this->manager->getMessages()["blocked"] ?? "";
			if($msg !== ""){
				$player->sendTip($msg);
			}
		}else{
			$warnDist = (float)($cfg["warning-distance"] ?? 0);
			if($warnDist > 0 && $rSq - $distSq <= $warnDist * $warnDist){
				$msg = $this->manager->getMessages()["warning"] ?? "";
				if($msg !== ""){
					$player->sendActionBarMessage($msg);
				}
			}
		}

		if($isInside && $wasOutside){
			$this->outside[$pid] = false;
			(new BorderEnterEvent($player))->call();
		}elseif(!$isInside && !$wasOutside){
			$this->outside[$pid] = true;
			(new BorderLeaveEvent($player))->call();
		}
	}
}
