<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\api\VisibleBorderAPI;
use NhanAZ\VisibleBorder\command\VBCommand;
use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\listener\PlayerMoveListener;
use NhanAZ\VisibleBorder\task\BorderSyncTask;
use NhanAZ\VisibleBorder\util\ActorIdentifierRegistrar;
use NhanAZ\libRegRsp\libRegRsp;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase implements Listener {
	private BorderManager $borderManager;
	private Config $messages;

	protected function onEnable() : void{
		if(!class_exists(libRegRsp::class)){
			$this->getLogger()->error("The libRegRsp virion is missing from this PHAR. Rebuild with DevTools using devtools.yml.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->saveDefaultConfig();
		$this->saveResource("messages.yml");
		$this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

		ActorIdentifierRegistrar::register(WorldBorderEntity::class, WorldBorderEntity::IDENTIFIER);

		libRegRsp::compileAndRegister($this, 'VisibleBorder Pack');
		if(!$this->isEnabled()){
			return;
		}

		$this->borderManager = new BorderManager($this);
		VisibleBorderAPI::init($this->borderManager);

		$cmd = new VBCommand($this->borderManager, $this->messages);
		$this->getServer()->getCommandMap()->register("visibleborder", $cmd);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getPluginManager()->registerEvents(new PlayerMoveListener($this->borderManager), $this);

		$intervalValue = $this->getConfig()->get("sync-interval-ticks", 40);
		$interval = is_int($intervalValue) && $intervalValue > 0 ? $intervalValue : 40;
		$this->getScheduler()->scheduleRepeatingTask(new BorderSyncTask($this->borderManager), $interval);
	}

	protected function onDisable() : void{
		if(class_exists(libRegRsp::class)){
			libRegRsp::unregister($this);
		}
	}

	public function onJoin(PlayerJoinEvent $event) : void{
		$this->borderManager->syncPlayerBorders($event->getPlayer());
	}

	public function getMessages() : Config{
		return $this->messages;
	}
}
