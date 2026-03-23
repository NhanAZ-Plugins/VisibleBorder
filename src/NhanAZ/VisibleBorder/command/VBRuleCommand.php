<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\command;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\ZoneRuleManager;
use NhanAZ\VisibleBorder\BorderPreset;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use RuntimeException;

final class VBRuleCommand {
	public function __construct(
		private BorderManager $borderManager,
		private ZoneRuleManager $rules,
		private BorderPreset $presets,
		private Config $messages
	){
	}

	public function handle(Player $sender, array $args) : bool{
		$sub = strtolower($args[0] ?? "");
		return match($sub){
			"rule" => $this->handleRule($sender, array_slice($args, 1)),
			"preset" => $this->handlePreset($sender, array_slice($args, 1)),
			default => false
		};
	}

	private function handleRule(Player $sender, array $args) : bool{
		$id = $args[0] ?? null;
		if($id === null){
			return $this->usage($sender);
		}
		$action = strtolower($args[1] ?? "");
		$world = $sender->getWorld();

		switch($action){
			case "pvp":
				$val = strtolower($args[2] ?? "true");
				if($val === "grace"){
					$seconds = (float)($args[3] ?? 0);
					$this->rules->setRule($id, $world, "pvp_grace", $seconds);
				}else{
					$this->rules->setRule($id, $world, "pvp", $this->toBool($val));
				}
				break;
			case "hunger":
				$val = strtolower($args[2] ?? "true");
				if($val === "freeze"){
					$freeze = (int)($args[3] ?? 20);
					$this->rules->setRule($id, $world, "hunger_freeze", $freeze);
				}else{
					$this->rules->setRule($id, $world, "hunger", $this->toBool($val));
				}
				break;
			case "block":
				$op = strtolower($args[2] ?? "");
				switch($op){
					case "place":
						$this->rules->setRule($id, $world, "block_place", $this->toBool($args[3] ?? "true"));
						break;
					case "break":
						$this->rules->setRule($id, $world, "block_break", $this->toBool($args[3] ?? "true"));
						break;
					case "all":
						$this->rules->setRule($id, $world, "block_all", $this->toBool($args[3] ?? "true"));
						break;
					case "whitelist":
						$this->rules->setRule($id, $world, "block_whitelist", (int)($args[3] ?? 0));
						break;
					case "blacklist":
						$this->rules->setRule($id, $world, "block_blacklist", (int)($args[3] ?? 0));
						break;
					default:
						return $this->usage($sender);
				}
				break;
			case "gamemode":
				if(strtolower($args[2] ?? "") === "restore"){
					$this->rules->setRule($id, $world, "gamemode_restore", $this->toBool($args[3] ?? "true"));
				}else{
					$this->rules->setRule($id, $world, "gamemode", $args[2] ?? "survival");
				}
				break;
			case "effect":
				$op = strtolower($args[2] ?? "");
				if($op === "add"){
					$effectId = (int)($args[3] ?? 1);
					$amp = (int)($args[4] ?? 0);
					$seconds = (float)($args[5] ?? 30);
					$this->rules->setRule($id, $world, "effect_add", [$effectId, $amp, $seconds]);
				}elseif($op === "clear"){
					$this->rules->setRule($id, $world, "effect_clear", true);
				}else{
					return $this->usage($sender);
				}
				break;
			case "list":
				$rules = $this->rules->getRulesForBorder($id, $world);
				$this->msg($sender, "rule-list-header", ["{id}" => $id]);
				foreach($rules as $k => $v){
					$val = is_array($v) ? json_encode($v) : (string)$v;
					$this->msg($sender, "rule-list-line", ["{key}" => $k, "{value}" => $val]);
				}
				return true;
			case "reset":
				$this->rules->resetRules($id, $world);
				$this->msg($sender, "rule-reset", ["{id}" => $id]);
				return true;
			default:
				return $this->usage($sender);
		}

		$this->msg($sender, "rule-updated", ["{id}" => $id]);
		return true;
	}

	private function handlePreset(Player $sender, array $args) : bool{
		$id = $args[0] ?? null;
		$preset = $args[1] ?? null;
		if($id === null || $preset === null){
			return $this->usage($sender);
		}
		$this->rules->applyPreset($id, $sender->getWorld(), $preset);
		$this->msg($sender, "preset-applied", ["{id}" => $id, "{preset}" => $preset]);
		return true;
	}

	private function toBool(string $raw) : bool{
		return in_array(strtolower($raw), ["1", "true", "yes", "on"], true);
	}

	private function msg(Player $sender, string $key, array $vars = []) : void{
		$text = (string)$this->messages->get($key, $key);
		$sender->sendMessage(strtr($text, $vars));
	}
}
