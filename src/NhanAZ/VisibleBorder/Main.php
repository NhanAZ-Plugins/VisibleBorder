<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use NhanAZ\VisibleBorder\command\VBCommand;
use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\listener\PlayerMoveListener;
use NhanAZ\VisibleBorder\task\BorderSyncTask;
use NhanAZ\VisibleBorder\util\ResourcePackUtil;
use NhanAZ\VisibleBorder\util\ActorIdentifierRegistrar;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase implements Listener {
	private BorderManager $borderManager;
	private Config $messages;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$this->saveResource("messages.yml");
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

		ActorIdentifierRegistrar::register(WorldBorderEntity::class, WorldBorderEntity::IDENTIFIER);

		ResourcePackUtil::compileAndSave($this);
		ResourcePackUtil::register($this);

		$this->borderManager = new BorderManager($this);
		VisibleBorderAPI::init($this->borderManager);

		$cmd = new VBCommand($this->borderManager, $this->messages);
		$this->getServer()->getCommandMap()->register("visibleborder", $cmd);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerMoveListener($this->borderManager), $this);

		$interval = (int)$this->getConfig()->get("sync-interval-ticks", 100);
		$this->getScheduler()->scheduleRepeatingTask(new BorderSyncTask($this->borderManager), $interval);
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
