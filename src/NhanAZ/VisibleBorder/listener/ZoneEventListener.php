<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\listener;

use NhanAZ\VisibleBorder\ZoneRuleManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class ZoneEventListener implements Listener {
	public function __construct(
		private ZoneRuleManager $rules,
		private Config $messages
	){}

	public function onDamage(EntityDamageByEntityEvent $event) : void{
		$victim = $event->getEntity();
		$damager = $event->getDamager();
		if(!$victim instanceof Player || !$damager instanceof Player){
			return;
		}
		if($damager->hasPermission("visibleborder.bypass") || $victim->hasPermission("visibleborder.bypass")){ return; } // FIXED: Enforced universal boundary disregard for designated moderation/bypass credentials
		if(!$this->rules->isPvpAllowedFor($victim)){
			$event->cancel();
			$msg = $this->messages->get("pvp-disabled", "PvP is disabled in this zone.");
			$damager->sendMessage($msg);
		}
	}

	public function onPlace(BlockPlaceEvent $event) : void{
		$player = $event->getPlayer();
		if(!$this->rules->canPlace($player, $event->getBlock()->getTypeId())){
			$event->cancel();
			$msg = $this->messages->get("block-place-deny", "You cannot place blocks here.");
			$player->sendMessage($msg);
		}
	}

	public function onBreak(BlockBreakEvent $event) : void{
		$player = $event->getPlayer();
		if(!$this->rules->canBreak($player, $event->getBlock()->getTypeId())){
			$event->cancel();
			$msg = $this->messages->get("block-break-deny", "You cannot break blocks here.");
			$player->sendMessage($msg);
		}
	}

	public function onInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		// Shortcut: treat interact as place/break gate when both are disabled
		if(!$this->rules->canPlace($player, $event->getBlock()->getTypeId()) || !$this->rules->canBreak($player, $event->getBlock()->getTypeId())){
			$event->cancel();
			$msg = $this->messages->get("block-interact-deny", "You cannot interact with blocks here.");
			$player->sendMessage($msg);
		}
	}

	public function onHunger(PlayerExhaustEvent $event) : void{
		$player = $event->getPlayer();
		$res = $this->rules->shouldCancelHunger($player);
		if($res["cancel"] === true){
			$event->cancel();
		}
		if($res["freeze"] !== null){
			$player->getHungerManager()->setFood($res["freeze"]);
		}
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$this->rules->handleQuit($event); // FIXED: restore gamemode on disconnect
	}
}
