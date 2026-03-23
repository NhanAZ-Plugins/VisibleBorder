<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use pocketmine\math\Vector2;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use pocketmine\entity\Entity;
use pocketmine\world\World;
use pocketmine\math\Vector3;

class BorderManager {
	/** @var array<string,array> */
	private array $worldConfigs = [];
	/** @var array<int,int> runtime ids keyed by playerId */
	private array $activeBorders = [];
	/** @var array<int,bool> */
	private array $hiddenPlayers = [];
	/** Base diameter (in blocks) of the model at scale 1.0, measured in-game. */
	private const MODEL_BASE_DIAMETER = 3.4;
	private const MODEL_OFFSET = 1.0; // outward bias so visual border sits about 1 block outside collision radius

	public function __construct(private Main $plugin){
		$this->reload();
	}

	public function reload() : void{
		$this->worldConfigs = $this->plugin->getConfig()->get("worlds", []);
	}

	public function getConfigForWorld(World $world) : ?array{
		$name = $world->getFolderName();
		if(!isset($this->worldConfigs[$name]) || !($this->worldConfigs[$name]["enabled"] ?? false)){
			return null;
		}
		return $this->worldConfigs[$name];
	}

	public function setBorder(string $worldName, Vector2 $center, float $radius) : void{
		$this->worldConfigs[$worldName] ??= [];
		$this->worldConfigs[$worldName]["enabled"] = true;
		$this->worldConfigs[$worldName]["center"] = [
			$this->snapToBlockCenter($center->getX()),
			$this->snapToBlockCenter($center->getY())
		];
		$this->worldConfigs[$worldName]["radius"] = $radius;
		$this->plugin->getConfig()->set("worlds", $this->worldConfigs);
		$this->plugin->getConfig()->save();
	}

	public function setCenter(string $worldName, Vector2 $center) : void{
		if(!isset($this->worldConfigs[$worldName])){
			return;
		}
		$this->worldConfigs[$worldName]["center"] = [
			$this->snapToBlockCenter($center->getX()),
			$this->snapToBlockCenter($center->getY())
		];
		$this->plugin->getConfig()->set("worlds", $this->worldConfigs);
		$this->plugin->getConfig()->save();
	}

	public function toggleHidden(Player $player, bool $hidden) : void{
		$this->hiddenPlayers[$player->getId()] = $hidden;
		if($hidden){
			$this->removeBorder($player);
		}else{
			$this->spawnBorder($player);
		}
	}

	public function spawnBorder(Player $player) : void{
		if(isset($this->hiddenPlayers[$player->getId()]) && $this->hiddenPlayers[$player->getId()] === true){
			$this->debug("skip spawn: player hidden", $player);
			return;
		}
		$cfg = $this->getConfigForWorld($player->getWorld());
		if($cfg === null){
			$this->debug("skip spawn: no config for world " . $player->getWorld()->getFolderName(), $player);
			return;
		}
		$center = new Vector3($cfg["center"][0] ?? 0, $player->getPosition()->y, $cfg["center"][1] ?? 0);
		$radius = (float)($cfg["radius"] ?? 0);
		if($radius <= 0){
			$this->debug("skip spawn: radius <= 0 (value={$radius})", $player);
			return;
		}
		$this->removeBorder($player);

		$rid = Entity::nextRuntimeId();
		$meta = new EntityMetadataCollection();
		$targetDiameter = ($radius + self::MODEL_OFFSET) * 2.0;
		$scale = max(0.01, $targetDiameter / self::MODEL_BASE_DIAMETER);
		$meta->setFloat(EntityMetadataProperties::SCALE, $scale);
		$meta->setGenericFlag(EntityMetadataFlags::INVISIBLE, false);
		$meta->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, false);
		$meta->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, false);
		$meta->setGenericFlag(EntityMetadataFlags::NO_AI, true);
		$meta->setGenericFlag(EntityMetadataFlags::FIRE_IMMUNE, true);
		// Keep hitbox tiny regardless of visual scale (min clamp > 0)
		$meta->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, PHP_FLOAT_MIN);
		$meta->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, PHP_FLOAT_MIN);

		$pk = AddActorPacket::create(
			$rid,
			$rid,
			WorldBorderEntity::IDENTIFIER,
			$center,
			null,
			0.0,
			0.0,
			0.0,
			0.0,
			[],
			$meta->getAll(),
			new PropertySyncData([], []),
			[]
		);
		$player->getNetworkSession()->sendDataPacket($pk);
		$this->activeBorders[$player->getId()] = $rid;
		$this->debug("spawned border rid={$rid} center=({$center->getX()}, {$center->getZ()}) radius={$radius}", $player);
	}

	public function removeBorder(Player $player) : void{
		$pid = $player->getId();
		if(isset($this->activeBorders[$pid])){
			$rid = $this->activeBorders[$pid];
			$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($rid));
			unset($this->activeBorders[$pid]);
			$this->debug("removed border rid={$rid}", $player);
		}
	}

	public function isInside(World $world, Vector3 $pos) : bool{
		$cfg = $this->getConfigForWorld($world);
		if($cfg === null){
			return true;
		}
		$cx = (float)($cfg["center"][0] ?? 0);
		$cz = (float)($cfg["center"][1] ?? 0);
		$radius = (float)($cfg["radius"] ?? 0);
		$dx = $pos->getX() - $cx;
		$dz = $pos->getZ() - $cz;
		return ($dx * $dx + $dz * $dz) <= ($radius * $radius);
	}

	public function getWorldConfigs() : array{
		return $this->worldConfigs;
	}

	public function getMessages() : array{
		return $this->plugin->getConfig()->get("messages", []);
	}

	public function hasBorder(Player $player) : bool{
		return isset($this->activeBorders[$player->getId()]);
	}

	public function getWarningDistance(World $world) : float{
		$cfg = $this->getConfigForWorld($world);
		return (float)($cfg["warning-distance"] ?? 10);
	}

	private function debug(string $message, ?Player $player = null) : void{
		$line = date('c') . " " . $message . PHP_EOL;
		@file_put_contents($this->plugin->getDataFolder() . "debug.log", $line, FILE_APPEND);
	}

	private function snapToBlockCenter(float $value) : float{
		return floor($value) + 0.5;
	}
}
