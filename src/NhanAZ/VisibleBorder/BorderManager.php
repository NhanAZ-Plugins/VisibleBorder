<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\model\Border;
use pocketmine\entity\Entity;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use RuntimeException;

final class BorderManager {
	private const STORAGE_FILE = "borders.yml";
	private const MODEL_BASE_DIAMETER = 3.4;
	private const MODEL_OFFSET = 1.0;
	private const TELEPORT_DISTANCE = 0.5;

	/** @var array<string,array<string,Border>> worldName => id => Border */
	private array $borders = [];
	/** @var array<int,array<string,int>> playerId => borderKey => runtimeId */
	private array $activeBorders = [];

	private Config $storage;

	public function __construct(private Main $plugin){
		$this->storage = new Config($plugin->getDataFolder() . self::STORAGE_FILE, Config::YAML);
		$this->loadBorders();
	}

	public function createBorder(string $id, World $world, float $size, Vector3 $center) : Border{
		if(isset($this->borders[$world->getFolderName()][$id])){
			throw new RuntimeException("Border '{$id}' already exists in world '{$world->getFolderName()}'.");
		}
		$border = new Border(
			$id,
			$world->getFolderName(),
			max(0.1, $size),
			$this->snapCenter($center),
			true
		);

		$this->borders[$world->getFolderName()][$id] = $border;
		$this->saveBorders();
		$this->spawnBorderForWorld($world, $border);
		return $border;
	}

	public function removeBorder(string $id, World $world) : bool{
		if(!isset($this->borders[$world->getFolderName()][$id])){
			return false;
		}
		$border = $this->borders[$world->getFolderName()][$id];
		$this->despawnBorderForWorld($world, $border);
		unset($this->borders[$world->getFolderName()][$id]);
		$this->saveBorders();
		return true;
	}

	public function clearWorld(World $world) : void{
		foreach($this->getBordersInWorld($world) as $border){
			$this->removeBorder($border->getId(), $world);
		}
	}

	public function getBorder(string $id, World $world) : ?Border{
		return $this->borders[$world->getFolderName()][$id] ?? null;
	}

	/** @return Border[] */
	public function getBordersInWorld(World $world) : array{
		return array_values($this->borders[$world->getFolderName()] ?? []);
	}

	public function setBorderSize(string $id, World $world, float $size) : void{
		$border = $this->requireBorder($id, $world);
		$border->setSize(max(0.1, $size));
		$this->saveBorders();
		$this->despawnBorderForWorld($world, $border);
		$this->spawnBorderForWorld($world, $border);
	}

	public function setBorderCenter(string $id, World $world, Vector3 $center) : void{
		$border = $this->requireBorder($id, $world);
		$border->setCenter($this->snapCenter($center));
		$this->saveBorders();
		$this->despawnBorderForWorld($world, $border);
		$this->spawnBorderForWorld($world, $border);
	}

	public function setBorderSolid(string $id, World $world, bool $solid) : void{
		$border = $this->requireBorder($id, $world);
		$border->setSolid($solid);
		$this->saveBorders();
	}

	public function isInsideBorder(Player $player, string $id) : bool{
		$border = $this->requireBorder($id, $player->getWorld());
		return $this->isInside($border, $player->getLocation());
	}

	public function handleMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		if($player->hasPermission("visibleborder.bypass")){
			return;
		}
		$to = $event->getTo();
		if($to === null){
			return;
		}
		$speed = $event->getFrom()->distance($to);
		foreach($this->borders[$player->getWorld()->getFolderName()] ?? [] as $border){
			$insideTo = $this->isInside($border, $to);
			$insideFrom = $this->isInside($border, $event->getFrom());

			if($border->isSolid()){
				if($insideFrom && !$insideTo){
					// prevent leaving
					$event->cancel();
					$this->applyBlindAndTeleportCenter($player, $border);
				}elseif(!$insideFrom && $insideTo){
					// prevent entering
					$event->cancel();
					$this->applyBlindAndTeleportCenter($player, $border);
				}
			}
		}
	}

	public function tickPlayer(Player $player) : void{
		if($player->hasPermission("visibleborder.bypass")){
			return;
		}
		$pos = $player->getLocation();
		foreach($this->borders[$player->getWorld()->getFolderName()] ?? [] as $border){
			$inside = $this->isInside($border, $pos);
			if($border->isSolid() && !$inside){
				$this->applyBlindAndTeleportCenter($player, $border);
			}
		}
	}

	public function syncPlayerBorders(Player $player) : void{
		foreach($this->borders[$player->getWorld()->getFolderName()] ?? [] as $border){
			$this->spawnBorderForPlayer($player, $border, true);
		}
	}

	private function applyBlindAndTeleportCenter(Player $player, Border $border) : void{
		$center = $border->getCenter();
		// Apply blindness (3s, level 255), hide particles/icon
		$blind = new EffectInstance(VanillaEffects::BLINDNESS(), 60, 254, false, false);
		$player->getEffects()->add($blind);
		// Teleport to center with small Y offset preserved
		$target = new Vector3($center->getX(), $player->getPosition()->getY(), $center->getZ());
		$player->teleport($target);
	}

	private function clampToBorder(Border $border, Vector3 $pos) : Location{
		$center = $border->getCenter();
		$direction = $pos->subtractVector($center);
		if($direction->lengthSquared() <= 0){
			return Location::fromObject($center, $pos->getWorld(), 0.0, 0.0);
		}
		$direction = $direction->normalize()->multiply(max(0.0, $border->getSize() - self::KNOCKBACK_DISTANCE));
		$target = $direction->addVector($center);
		return Location::fromObject(new Vector3($target->getX(), $pos->getY(), $target->getZ()), $pos->getWorld(), 0.0, 0.0);
	}

	private function isInside(Border $border, Vector3 $pos) : bool{
		$dx = $pos->getX() - $border->getCenter()->getX();
		$dz = $pos->getZ() - $border->getCenter()->getZ();
		return ($dx * $dx + $dz * $dz) <= ($border->getSize() * $border->getSize());
	}

	private function spawnBorderForWorld(World $world, Border $border) : void{
		foreach($world->getPlayers() as $player){
			$this->spawnBorderForPlayer($player, $border, true);
		}
	}

	private function despawnBorderForWorld(World $world, Border $border) : void{
		$key = $this->makeKey($world, $border->getId());
		foreach($world->getPlayers() as $player){
			$pid = $player->getId();
			if(isset($this->activeBorders[$pid][$key])){
				$rid = $this->activeBorders[$pid][$key];
				$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($rid));
				unset($this->activeBorders[$pid][$key]);
			}
		}
	}

	private function spawnBorderForPlayer(Player $player, Border $border, bool $forceMove = false) : void{
		if($border->getSize() <= 0){
			return;
		}
		$key = $this->makeKey($player->getWorld(), $border->getId());
		$pid = $player->getId();
		$center = new Vector3(
			$border->getCenter()->getX(),
			$border->getCenter()->getY(),
			$border->getCenter()->getZ()
		);

		// Always respawn fresh actor to avoid protocol mismatches
		if(isset($this->activeBorders[$pid][$key])){
			$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->activeBorders[$pid][$key]));
		}

		$rid = Entity::nextRuntimeId();
		$meta = new EntityMetadataCollection();
		$targetDiameter = ($border->getSize() + self::MODEL_OFFSET) * 2.0;
		$scale = max(0.01, $targetDiameter / self::MODEL_BASE_DIAMETER);
		$meta->setFloat(EntityMetadataProperties::SCALE, $scale);
		$meta->setGenericFlag(EntityMetadataFlags::INVISIBLE, false);
		$meta->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, false);
		$meta->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, false);
		$meta->setGenericFlag(EntityMetadataFlags::NO_AI, true);
		$meta->setGenericFlag(EntityMetadataFlags::FIRE_IMMUNE, true);
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
		$this->activeBorders[$pid][$key] = $rid;
	}

	private function loadBorders() : void{
		$data = $this->storage->getAll();
		foreach($data as $worldName => $borders){
			foreach($borders as $id => $entry){
				$this->borders[$worldName][$id] = Border::fromArray((string)$id, (string)$worldName, (array)$entry);
			}
		}
	}

	private function saveBorders() : void{
		$out = [];
		foreach($this->borders as $worldName => $borders){
			foreach($borders as $id => $border){
				$out[$worldName][$id] = $border->toArray();
			}
		}
		$this->storage->setAll($out);
		$this->storage->save();
	}

	private function requireBorder(string $id, World $world) : Border{
		$border = $this->getBorder($id, $world);
		if($border === null){
			throw new RuntimeException("Border '{$id}' does not exist in world '{$world->getFolderName()}'.");
		}
		return $border;
	}

	private function makeKey(World $world, string $id) : string{
		return $world->getFolderName() . ":" . $id;
	}

	private function snapCenter(Vector3 $center) : Vector3{
		return new Vector3(floor($center->getX()) + 0.5, $center->getY(), floor($center->getZ()) + 0.5);
	}
}
