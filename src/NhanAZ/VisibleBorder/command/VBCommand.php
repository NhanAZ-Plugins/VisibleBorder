<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\command;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\command\VBRuleCommand;
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
		private Config $messages,
		private VBRuleCommand $ruleCommand
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
		if(in_array($sub, ["rule", "preset"], true)){
			if($this->ruleCommand->handle($sender, $args)){
				return true;
			}
		}
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
		$defaultSize = (float)($this->manager->getDefaults()["min_size"] ?? 5.0);
		$this->manager->createBorder($id, $sender->getWorld(), $defaultSize, $sender->getPosition());
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
		$template = $this->messages->get("border-info", "{id}: size {size}, min {min}, center ({x},{z}), solid {solid}, damage {dmg} @ {ddist}/{ddelay}s, kb {kbp} @ {kbdist}/{kbdelay}s, onZero {onzero}");
		$sender->sendMessage(strtr($template, [
			"{id}" => $border->getId(),
			"{size}" => (string)$border->getSize(),
			"{min}" => (string)$border->getMinSize(),
			"{x}" => (string)$border->getCenter()->getX(),
			"{z}" => (string)$border->getCenter()->getZ(),
			"{solid}" => $border->isSolid() ? "true" : "false",
			"{dmg}" => (string)$border->getDamageAmount(),
			"{ddist}" => (string)$border->getDamageDistance(),
			"{ddelay}" => (string)$border->getDamageDelay(),
			"{kbp}" => (string)$border->getKnockbackPower(),
			"{kbdist}" => (string)$border->getKnockbackDistance(),
			"{kbdelay}" => (string)$border->getKnockbackDelay(),
			"{onzero}" => $border->getOnZeroAction()
		]));
		return true;
	}

	private function set(Player $sender, array $args) : bool{
		$id = $args[1] ?? null;
		$property = strtolower($args[2] ?? "");
		if($id === null || $property === ""){
			return $this->usage($sender);
		}
		$world = $sender->getWorld();

		return match($property){
			"size" => $this->handleSize($sender, $id, $args),
			"minsize" => $this->handleMinSize($sender, $id, $args),
			"lifetime" => $this->handleLifetime($sender, $id, $args),
			"center" => $this->handleCenter($sender, $id, $args),
			"solid" => $this->handleSolid($sender, $id, $args),
			"speed" => $this->handleSpeed($sender, $id, $args),
			"damage" => $this->handleDamage($sender, $id, $args),
			"knockback" => $this->handleKnockback($sender, $id, $args),
			"onzero" => $this->handleOnZero($sender, $id, $args),
			default => $this->usage($sender)
		};
	}

	private function handleSize(Player $sender, string $id, array $args) : bool{
		$world = $sender->getWorld();
		if(count($args) >= 5){ // gradual
			$target = (float)$args[3];
			$seconds = (float)$args[4];
			$this->manager->shrinkBorder($id, $world, $target, $seconds);
			$this->msg($sender, "border-size-anim", ["{id}" => $id, "{size}" => (string)$target, "{seconds}" => (string)$seconds]);
			return true;
		}
		$value = (float)($args[3] ?? 0);
		$this->manager->setBorderSize($id, $world, $value);
		$this->msg($sender, "border-size-set", ["{id}" => $id, "{size}" => (string)$value]);
		return true;
	}

	private function handleMinSize(Player $sender, string $id, array $args) : bool{
		$value = (float)($args[3] ?? 0);
		$this->manager->setBorderMinSize($id, $sender->getWorld(), $value);
		$this->msg($sender, "border-minsize-set", ["{id}" => $id, "{size}" => (string)$value]);
		return true;
	}

	private function handleLifetime(Player $sender, string $id, array $args) : bool{
		$seconds = (float)($args[3] ?? 0);
		$this->manager->setBorderLifetime($id, $sender->getWorld(), $seconds);
		$this->msg($sender, "border-lifetime-set", ["{id}" => $id, "{seconds}" => (string)$seconds]);
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

	private function handleSpeed(Player $sender, string $id, array $args) : bool{
		$value = (float)($args[3] ?? 0);
		$this->manager->setBorderSpeed($id, $sender->getWorld(), $value);
		$this->msg($sender, "border-speed-set", ["{id}" => $id, "{value}" => (string)$value]);
		return true;
	}

	private function handleDamage(Player $sender, string $id, array $args) : bool{
		$aspect = strtolower($args[3] ?? "");
		$value = (float)($args[4] ?? 0);
		$border = $this->manager->getBorder($id, $sender->getWorld());
		if($border === null){
			$this->msg($sender, "border-not-found", ["{id}" => $id]);
			return true;
		}
		$amount = $border->getDamageAmount();
		$distance = $border->getDamageDistance();
		$delay = $border->getDamageDelay();
		switch($aspect){
			case "amount":
				$amount = $value;
				break;
			case "distance":
				$distance = $value;
				break;
			case "delay":
				$delay = $value;
				break;
			default:
				return $this->usage($sender);
		}
		$this->manager->setDamageConfig($id, $sender->getWorld(), $amount, $distance, $delay);
		$this->msg($sender, "border-damage-set", ["{id}" => $id]);
		return true;
	}

	private function handleKnockback(Player $sender, string $id, array $args) : bool{
		$aspect = strtolower($args[3] ?? "");
		$value = (float)($args[4] ?? 0);
		$border = $this->manager->getBorder($id, $sender->getWorld());
		if($border === null){
			$this->msg($sender, "border-not-found", ["{id}" => $id]);
			return true;
		}
		$power = $border->getKnockbackPower();
		$distance = $border->getKnockbackDistance();
		$delay = $border->getKnockbackDelay();
		switch($aspect){
			case "power":
				$power = $value;
				break;
			case "distance":
				$distance = $value;
				break;
			case "delay":
				$delay = $value;
				break;
			default:
				return $this->usage($sender);
		}
		$this->manager->setKnockbackConfig($id, $sender->getWorld(), $power, $distance, $delay);
		$this->msg($sender, "border-knockback-set", ["{id}" => $id]);
		return true;
	}

	private function handleOnZero(Player $sender, string $id, array $args) : bool{
		$action = strtolower($args[3] ?? "");
		if($action === ""){
			return $this->usage($sender);
		}
		if($action === "damage"){
			$amount = (float)($args[4] ?? 1.0);
			$action = "damage " . $amount;
		}
		$this->manager->setOnZeroAction($id, $sender->getWorld(), $action);
		$this->msg($sender, "border-onzero-set", ["{id}" => $id, "{action}" => $action]);
		return true;
	}

	private function usage(Player $sender) : bool{
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
