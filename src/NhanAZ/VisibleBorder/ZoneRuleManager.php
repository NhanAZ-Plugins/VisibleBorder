<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\model\Border;
use pocketmine\entity\effect\EffectIdMap;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;

final class ZoneRuleManager {
	private array $inside = []; // playerId => [borderKey => true]
	private array $graceUntil = []; // borderKey => timestamp
	private array $gmCache = []; // playerId => [borderKey => GameMode]

	public function __construct(
		private BorderManager $borderManager,
		private BorderPreset $presets
	){}

	public function setRule(string $borderId, World $world, string $rule, mixed $value) : void{
		$border = $this->getBorderOrFail($borderId, $world);
		$rules = $border->getRules();
		$key = $this->makeKey($world, $borderId);

		switch($rule){
			case "pvp":
				$rules["pvp"] = (bool)$value;
				break;
			case "pvp_grace":
				$seconds = max(0.0, (float)$value);
				$rules["pvp_grace_seconds"] = $seconds;
				$rules["pvp_grace_until"] = microtime(true) + $seconds; // FIXED: player leaves mid-grace-period
				$this->graceUntil[$key] = $rules["pvp_grace_until"];
				break;

			case "hunger":
				$rules["hunger"] = (bool)$value; // true = hunger drains, false = disabled
				break;
			case "hunger_freeze":
				$rules["hunger_freeze"] = max(0, min(20, (int)$value));
				break;

			case "block_place":
				$rules["block_place"] = (bool)$value;
				break;
			case "block_break":
				$rules["block_break"] = (bool)$value;
				break;
			case "block_all":
				$allow = !(bool)$value; // if true => disable both
				$rules["block_place"] = $allow;
				$rules["block_break"] = $allow;
				break;
			case "block_whitelist":
				$id = (int)$value;
				$rules["block_whitelist"] ??= [];
				if(!in_array($id, $rules["block_whitelist"], true)){
					$rules["block_whitelist"][] = $id;
				}
				break;
			case "block_blacklist":
				$id = (int)$value;
				$rules["block_blacklist"] ??= [];
				if(!in_array($id, $rules["block_blacklist"], true)){
					$rules["block_blacklist"][] = $id;
				}
				break;

			case "gamemode":
				$rules["gamemode"] = $value;
				break;
			case "gamemode_restore":
				$rules["gamemode_restore"] = (bool)$value;
				break;

			case "effect_add":
				[$effectId, $amp, $seconds] = $value;
				$rules["effects"] ??= [];
				$rules["effects"][] = [
					"id" => (int)$effectId,
					"amplifier" => (int)$amp,
					"seconds" => (float)$seconds
				];
				break;
			case "effect_clear":
				$rules["effect_clear"] = (bool)$value;
				break;

			default:
				throw new RuntimeException("Unknown rule: {$rule}");
		}

		$border->setRules($rules);
		$this->borderManager->updateBorder($border);
	}

	public function getRule(string $borderId, World $world, string $rule) : mixed{
		$border = $this->getBorderOrFail($borderId, $world);
		$rules = $border->getRules();
		return $rules[$rule] ?? null;
	}

	public function resetRules(string $borderId, World $world) : void{
		$border = $this->getBorderOrFail($borderId, $world);
		$border->setRules([]);
		$this->borderManager->updateBorder($border);
		$key = $this->makeKey($world, $borderId);
		unset($this->graceUntil[$key]);
		// FIXED: gamemode restore on reset
	}

	public function applyPreset(string $borderId, World $world, string $preset) : void{
		$rules = $this->presets->getPreset($preset);
		if($rules === null){
			throw new RuntimeException("Preset '{$preset}' not found.");
		}
		foreach($rules as $rule => $value){
			$this->setRule($borderId, $world, $rule, $value);
		}
	}

	public function getRulesForBorder(string $borderId, World $world) : array{
		$border = $this->getBorderOrFail($borderId, $world);
		return $border->getRules();
	}

	public function tickPlayer(Player $player) : void{
		$pid = $player->getId();
		$current = [];
		foreach($this->borderManager->getBordersInWorld($player->getWorld()) as $border){
			if($this->isInside($border, $player->getLocation())){
				$key = $this->makeKeyByBorder($border);
				$current[$key] = $border;
				if(!isset($this->inside[$pid][$key])){
					$this->onEnter($player, $border);
				}
			}
		}

		// handle exits
		foreach($this->inside[$pid] ?? [] as $key => $_){
			if(!isset($current[$key])){
				[$worldName, $borderId] = explode(":", $key, 2);
				$border = $this->borderManager->getBorder($borderId, $player->getWorld());
				if($border !== null){
					$this->onLeave($player, $border);
				}
			}
		}

		$this->inside[$pid] = array_fill_keys(array_keys($current), true);
	}

	public function handleQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		$pid = $player->getId();
		if(isset($this->gmCache[$pid])){
			foreach($this->gmCache[$pid] as $gm){
				$player->setGamemode($gm); // FIXED: gamemode restore on disconnect
			}
		}
		unset($this->gmCache[$pid], $this->inside[$pid]);
	}

	public function isPvpAllowedFor(Player $player) : bool{
		foreach($this->borderManager->getBordersInWorld($player->getWorld()) as $border){
			if(!$this->isInside($border, $player->getLocation())){
				continue;
			}
			if(!$this->isPvpAllowed($border)){
				return false;
			}
		}
		return true;
	}

	public function canPlace(Player $player, int $typeId) : bool{
		return $this->checkBlockRule($player, $typeId, "block_place");
	}

	public function canBreak(Player $player, int $typeId) : bool{
		return $this->checkBlockRule($player, $typeId, "block_break");
	}

	public function shouldCancelHunger(Player $player) : array{
		$result = ["cancel" => false, "freeze" => null];
		foreach($this->borderManager->getBordersInWorld($player->getWorld()) as $border){
			if(!$this->isInside($border, $player->getLocation())){
				continue;
			}
			$rules = $border->getRules();
			if(($rules["hunger"] ?? true) === false){
				$result["cancel"] = true;
			}
			if(isset($rules["hunger_freeze"])){
				$result["freeze"] = (int)$rules["hunger_freeze"];
			}
		}
		return $result;
	}

	private function onEnter(Player $player, Border $border) : void{
		$pid = $player->getId();
		$key = $this->makeKeyByBorder($border);
		$this->inside[$pid][$key] = true;
		$rules = $border->getRules();

		if(isset($rules["gamemode"])){
			if(!isset($this->gmCache[$pid][$key])){
				$this->gmCache[$pid][$key] = $player->getGamemode();
			}
			try{
				$player->setGamemode(GameMode::fromString((string)$rules["gamemode"]));
			}catch(\InvalidArgumentException){
				$player->setGamemode(GameMode::SURVIVAL()); // FIXED: invalid gamemode string fallback
			}
		}

		if(($rules["effect_clear"] ?? false) === true){
			$player->getEffects()->clear(); // FIXED: remove effects on enter when configured
		}
		foreach($rules["effects"] ?? [] as $effectData){
			$effect = EffectIdMap::getInstance()->fromId((int)($effectData["id"] ?? -1));
			if($effect === null){
				continue;
			}
			$seconds = max(1, (int)($effectData["seconds"] ?? 1));
			$amp = max(0, (int)($effectData["amplifier"] ?? 0));
			$player->getEffects()->add(new EffectInstance($effect, $seconds * 20, $amp));
		}
		if(isset($rules["hunger_freeze"])){
			$player->getHungerManager()->setFood($rules["hunger_freeze"]);
		}
	}

	private function onLeave(Player $player, Border $border) : void{
		$pid = $player->getId();
		$key = $this->makeKeyByBorder($border);
		$rules = $border->getRules();
		if(($rules["gamemode_restore"] ?? false) && isset($this->gmCache[$pid][$key])){
			$player->setGamemode($this->gmCache[$pid][$key]); // FIXED: restore on exit
		}
		unset($this->gmCache[$pid][$key], $this->inside[$pid][$key]);
	}

	private function isPvpAllowed(Border $border) : bool{
		$rules = $border->getRules();
		if(isset($rules["pvp"]) && $rules["pvp"] === false){
			return false;
		}
		$key = $this->makeKeyByBorder($border);
		$until = $rules["pvp_grace_until"] ?? $this->graceUntil[$key] ?? null;
		if($until !== null && microtime(true) < (float)$until){
			return false; // FIXED: grace period disables PvP until timer ends
		}
		return true;
	}

	private function checkBlockRule(Player $player, int $typeId, string $flag) : bool{
		foreach($this->borderManager->getBordersInWorld($player->getWorld()) as $border){
			if(!$this->isInside($border, $player->getLocation())){
				continue;
			}
			$rules = $border->getRules();
			if(in_array($typeId, $rules["block_blacklist"] ?? [], true)){
				return false;
			}
			if(!empty($rules["block_whitelist"] ?? []) && !in_array($typeId, $rules["block_whitelist"], true)){
				return false;
			}
			if(isset($rules[$flag]) && $rules[$flag] === false){
				return false;
			}
		}
		return true;
	}

	private function isInside(Border $border, \pocketmine\math\Vector3 $pos) : bool{
		$dx = $pos->getX() - $border->getCenter()->getX();
		$dz = $pos->getZ() - $border->getCenter()->getZ();
		return ($dx * $dx + $dz * $dz) <= ($border->getSize() * $border->getSize());
	}

	private function getBorderOrFail(string $id, World $world) : Border{
		$border = $this->borderManager->getBorder($id, $world);
		if($border === null){
			throw new RuntimeException("Border '{$id}' does not exist in world '{$world->getFolderName()}'.");
		}
		return $border;
	}

	private function makeKey(World $world, string $id) : string{
		return $world->getFolderName() . ":" . $id;
	}

	private function makeKeyByBorder(Border $border) : string{
		return $border->getWorldName() . ":" . $border->getId();
	}
}
