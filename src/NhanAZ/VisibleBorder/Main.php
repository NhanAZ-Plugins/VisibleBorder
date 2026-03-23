<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use NhanAZ\VisibleBorder\command\BorderCommand;
use NhanAZ\VisibleBorder\listener\PlayerMoveListener;
use NhanAZ\VisibleBorder\task\BorderSyncTask;
use NhanAZ\VisibleBorder\util\ResourcePackUtil;
use NhanAZ\VisibleBorder\util\ActorIdentifierRegistrar;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;

class Main extends PluginBase implements Listener {
	private BorderManager $borderManager;
	private ?TaskHandler $syncTask = null;

	protected function onEnable() : void{
		ActorIdentifierRegistrar::register(WorldBorderEntity::class, WorldBorderEntity::IDENTIFIER);

		$this->saveDefaultConfig();
		@mkdir($this->getDataFolder(), 0777, true);
		ResourcePackUtil::compileAndSave($this);
		ResourcePackUtil::register($this);

		$this->borderManager = new BorderManager($this);
		VisibleBorderAPI::init($this, $this->borderManager);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerMoveListener($this->borderManager), $this);
		$this->getServer()->getCommandMap()->register("visibleborder", new BorderCommand($this->borderManager));

		$interval = (int)$this->getConfig()->get("sync-interval-ticks", 100);
		$this->syncTask = $this->getScheduler()->scheduleRepeatingTask(new BorderSyncTask($this->borderManager), $interval);
	}

	protected function onDisable() : void{
		if($this->syncTask !== null){
			$this->syncTask->cancel();
		}
		ResourcePackUtil::unregister($this);
	}

	public function onJoin(PlayerJoinEvent $event) : void{
		if($this->getConfig()->get("send-border-on-join", true)){
			$this->borderManager->spawnBorder($event->getPlayer());
		}
	}
}
