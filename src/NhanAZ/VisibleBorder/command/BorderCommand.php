<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\command;

use NhanAZ\VisibleBorder\BorderManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector2;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BorderCommand extends Command {
	public function __construct(private BorderManager $manager){
		parent::__construct("border", "Manage visible border", "/border <set|center|show|hide|info>", []);
		$this->setPermission("visibleborder.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Chỉ dùng trong game.");
			return;
		}
		$sub = $args[0] ?? "";
		switch($sub){
			case "set":
				$r = (float)($args[1] ?? 0);
				if($r <= 0){
					$sender->sendMessage(TextFormat::RED . "Bán kính phải > 0");
					return;
				}
				$center = new Vector2($sender->getPosition()->getX(), $sender->getPosition()->getZ());
				$this->manager->setBorder($sender->getWorld()->getFolderName(), $center, $r);
				$sender->sendMessage(TextFormat::GREEN . "Đã đặt border radius {$r} tại world " . $sender->getWorld()->getFolderName());
				$this->manager->spawnBorder($sender);
				return;

			case "center":
				if(count($args) < 3){
					$sender->sendMessage(TextFormat::RED . "/border center <x> <z>");
					return;
				}
				$cx = (float)$args[1];
				$cz = (float)$args[2];
				$this->manager->setCenter($sender->getWorld()->getFolderName(), new Vector2($cx, $cz));
				$sender->sendMessage(TextFormat::GREEN . "Đã đặt tâm ({$cx}, {$cz}).");
				$this->manager->spawnBorder($sender);
				return;

			case "show":
				$this->manager->toggleHidden($sender, false);
				$sender->sendMessage(TextFormat::GREEN . "Đã bật hiển thị border.");
				return;

			case "hide":
				$this->manager->toggleHidden($sender, true);
				$sender->sendMessage(TextFormat::YELLOW . "Đã ẩn border của bạn.");
				return;

			case "info":
			default:
				$cfg = $this->manager->getConfigForWorld($sender->getWorld());
				if($cfg === null){
					$sender->sendMessage(TextFormat::YELLOW . "World này chưa có border.");
					return;
				}
				$sender->sendMessage(TextFormat::AQUA . "Border world " . $sender->getWorld()->getFolderName() .
					" tâm (" . $cfg["center"][0] . ", " . $cfg["center"][1] . "), bán kính " . $cfg["radius"]);
				return;
		}
	}
}
