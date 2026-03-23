<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\command;

use NhanAZ\VisibleBorder\BorderManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class VBCommand extends Command {
	public function __construct(
		private BorderManager $manager,
		private Config $messages
	){
		parent::__construct("vb", "VisibleBorder management", "/vb help");
		$this->setPermission("visibleborder.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}
		if(!$sender instanceof Player){
			$this->msg($sender, "ingame-only");
			return true;
		}
		$sub = strtolower($args[0] ?? "help");
		try{
			return match($sub){
				"create" => $this->create($sender, $args),
				"remove" => $this->remove($sender, $args),
				"clear"  => $this->clear($sender),
				"list"   => $this->list($sender),
				"info"   => $this->info($sender, $args),
				"set"    => $this->set($sender, $args),
				default  => $this->usage($sender)
			};
		}catch(RuntimeException $e){
			$sender->sendMessage(TextFormat::RED . $e->getMessage());
			return true;
		}
	}

	private function create(Player $sender, array $args) : bool{
		$id = $args[1] ?? null;
		if($id === null){
			return $this->usage($sender);
		}
		$this->manager->createBorder($id, $sender->getWorld(), 5.0, $sender->getPosition());
		$this->msg($sender, "border-created", ["{id}" => $id]);
		return true;
	}

	private function remove(Player $sender, array $args) : bool{
		$id = $args[1] ?? null;
		if($id === null){
			return $this->usage($sender);
		}
		if($this->manager->removeBorder($id, $sender->getWorld())){
			$this->msg($sender, "border-removed", ["{id}" => $id]);
		}else{
			$this->msg($sender, "border-not-found", ["{id}" => $id]);
		}
		return true;
	}

	private function clear(Player $sender) : bool{
		$this->manager->clearWorld($sender->getWorld());
		$this->msg($sender, "border-cleared");
		return true;
	}

	private function list(Player $sender) : bool{
		$borders = $this->manager->getBordersInWorld($sender->getWorld());
		if(count($borders) === 0){
			$this->msg($sender, "border-empty");
			return true;
		}
		$this->msg($sender, "border-list-header", ["{count}" => (string)count($borders)]);
		foreach($borders as $border){
			$line = $this->messages->get("border-list-line", "{id}: size {size} center ({x}, {z})");
			$line = strtr($line, [
				"{id}" => $border->getId(),
				"{size}" => (string)$border->getSize(),
				"{x}" => (string)$border->getCenter()->getX(),
				"{z}" => (string)$border->getCenter()->getZ()
			]);
			$sender->sendMessage($line);
		}
		return true;
	}

	private function info(Player $sender, array $args) : bool{
		$id = $args[1] ?? null;
		if($id === null){
			return $this->usage($sender);
		}
		$border = $this->manager->getBorder($id, $sender->getWorld());
		if($border === null){
			$this->msg($sender, "border-not-found", ["{id}" => $id]);
			return true;
		}
		$template = $this->messages->get("border-info", "{id}: size {size}, center ({x},{z}), solid {solid}");
		$sender->sendMessage(strtr($template, [
			"{id}" => $border->getId(),
			"{size}" => (string)$border->getSize(),
			"{x}" => (string)$border->getCenter()->getX(),
			"{z}" => (string)$border->getCenter()->getZ(),
			"{solid}" => $border->isSolid() ? "true" : "false"
		]));
		return true;
	}

	private function set(Player $sender, array $args) : bool{
		$id = $args[1] ?? null;
		$property = strtolower($args[2] ?? "");
		if($id === null || $property === ""){
			return $this->usage($sender);
		}
		return match($property){
			"size" => $this->handleSize($sender, $id, $args),
			"center" => $this->handleCenter($sender, $id, $args),
			"solid" => $this->handleSolid($sender, $id, $args),
			default => $this->usage($sender)
		};
	}

	private function handleSize(Player $sender, string $id, array $args) : bool{
		$value = (float)($args[3] ?? $args[2] ?? 0);
		$this->manager->setBorderSize($id, $sender->getWorld(), $value);
		$this->msg($sender, "border-size-set", ["{id}" => $id, "{size}" => (string)$value]);
		return true;
	}

	private function handleCenter(Player $sender, string $id, array $args) : bool{
		if(count($args) >= 5){
			$x = (float)$args[3];
			$z = (float)$args[4];
			$pos = new Vector3($x, $sender->getPosition()->getY(), $z);
			$this->manager->setBorderCenter($id, $sender->getWorld(), $pos);
			$this->msg($sender, "border-center-set", ["{id}" => $id, "{x}" => (string)$x, "{z}" => (string)$z]);
			return true;
		}
		$this->manager->setBorderCenter($id, $sender->getWorld(), $sender->getPosition());
		$this->msg($sender, "border-center-set", [
			"{id}" => $id,
			"{x}" => (string)$sender->getPosition()->getX(),
			"{z}" => (string)$sender->getPosition()->getZ()
		]);
		return true;
	}

	private function handleSolid(Player $sender, string $id, array $args) : bool{
		$value = strtolower($args[3] ?? "");
		$solid = $value === "true" || $value === "1" || $value === "yes";
		$this->manager->setBorderSolid($id, $sender->getWorld(), $solid);
		$this->msg($sender, "border-solid-set", ["{id}" => $id, "{value}" => $solid ? "true" : "false"]);
		return true;
	}

	private function usage(CommandSender $sender) : bool{
		$help = $this->messages->get("help", null);
		if(is_array($help)){
			foreach($help as $line){
				$sender->sendMessage(TextFormat::YELLOW . (string)$line);
			}
			return true;
		}
		$sender->sendMessage(TextFormat::YELLOW . $this->messages->get("usage", "/vb create/remove/set/info/list/clear"));
		return true;
	}

	private function msg(CommandSender $sender, string $key, array $vars = []) : void{
		$text = (string)$this->messages->get($key, $key);
		$sender->sendMessage(strtr($text, $vars));
	}
}
