<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use NhanAZ\VisibleBorder\command\VBCommand;
use NhanAZ\VisibleBorder\command\VBRuleCommand;
use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\listener\PlayerMoveListener;
use NhanAZ\VisibleBorder\listener\ZoneEventListener;
use NhanAZ\VisibleBorder\task\BorderSyncTask;
use NhanAZ\VisibleBorder\util\ResourcePackUtil;
use NhanAZ\VisibleBorder\util\ActorIdentifierRegistrar;
use NhanAZ\VisibleBorder\ZoneRuleManager;
use NhanAZ\VisibleBorder\BorderPreset;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase implements Listener {
	private BorderManager $borderManager;
	private ZoneRuleManager $zoneRules;
	private Config $messages;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$this->saveResource("messages.yml");
		$this->saveResource("presets.yml");
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
		$presets = new BorderPreset($this->getDataFolder() . "presets.yml");

		ActorIdentifierRegistrar::register(WorldBorderEntity::class, WorldBorderEntity::IDENTIFIER);

		ResourcePackUtil::compileAndSave($this);
		ResourcePackUtil::register($this);

		$this->borderManager = new BorderManager($this);
		$this->zoneRules = new ZoneRuleManager($this->borderManager, $presets);
		VisibleBorderAPI::init($this->borderManager, $this->zoneRules);

		$ruleHelper = new \NhanAZ\VisibleBorder\command\VBRuleCommand($this->borderManager, $this->zoneRules, $presets, $this->messages);
		$cmd = new VBCommand($this->borderManager, $this->messages, $ruleHelper);
		$this->getServer()->getCommandMap()->register("visibleborder", $cmd);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerMoveListener($this->borderManager, $this->zoneRules), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ZoneEventListener($this->zoneRules, $this->messages), $this);

		$interval = (int)$this->getConfig()->get("sync-interval-ticks", 100);
		$this->getScheduler()->scheduleRepeatingTask(new BorderSyncTask($this->borderManager, $this->zoneRules), $interval);
	}

	protected function onDisable() : void{
		ResourcePackUtil::unregister($this);
	}

	public function onJoin(PlayerJoinEvent $event) : void{
		$this->borderManager->syncPlayerBorders($event->getPlayer());
	}

	public function getMessages() : Config{
		return $this->messages;
	}
}
