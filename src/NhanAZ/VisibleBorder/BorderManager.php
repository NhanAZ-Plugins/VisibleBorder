<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use NhanAZ\VisibleBorder\entity\WorldBorderEntity;
use NhanAZ\VisibleBorder\model\Border;
use NhanAZ\VisibleBorder\BorderShrinkTask;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use RuntimeException;

final class BorderManager {
	private const STORAGE_FILE = "borders.yml";
	private const MODEL_BASE_DIAMETER = 3.4;
	private const MODEL_OFFSET = 1.0; // outward bias so visual border sits about 1 block outside collision radius

	/** @var array<string,array<string,Border>> worldName => id => Border */
	private array $borders = [];
	/** @var array<int,array<string,int>> playerId => borderKey => runtimeId */
	private array $activeBorders = [];
	/** @var array<string,TaskHandler> */
	private array $shrinkTasks = [];
	/** @var array<string,TaskHandler> */
	private array $lifetimeTasks = [];
	/** @var array<int,array<string,float>> */
	private array $damageCooldowns = [];
	/** @var array<int,array<string,float>> */
	private array $knockbackCooldowns = [];

	private array $defaults;
	private Config $storage;

	public function __construct(private Main $plugin){
		$this->defaults = $plugin->getConfig()->get("defaults", []);
		$this->storage = new Config($plugin->getDataFolder() . self::STORAGE_FILE, Config::YAML);
		$this->loadBorders();
		$this->restoreLifetimes();
	}

	public function createBorder(string $id, World $world, float $size, Vector3 $center) : Border{
		$key = $this->makeKey($world, $id);
		if(isset($this->borders[$world->getFolderName()][$id])){
			throw new RuntimeException("Border '{$id}' already exists in world '{$world->getFolderName()}'.");
		}
		$minSize = max($this->getDefaultFloat("min_size") ?: 1.0, 0.0001);
		$size = max($size, $minSize);
		$border = new Border(
			$id,
			$world->getFolderName(),
			$size,
			$minSize,
			$this->getDefaultFloat("speed"),
			$this->snapCenter($center),
			$this->getDefaultBool("solid"),
			$this->getDefaultFloat("damage.amount"),
			$this->getDefaultFloat("damage.distance"),
			$this->getDefaultFloat("damage.delay"),
			$this->getDefaultFloat("knockback.power"),
			$this->getDefaultFloat("knockback.distance"),
			$this->getDefaultFloat("knockback.delay"),
			(string)$this->defaults["on_zero"] ?? "kill",
			null
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
		$this->cancelShrink($border);
		$this->cancelLifetime($border);
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

	public function shrinkBorder(string $id, World $world, float $targetSize, float $seconds) : void{
		$border = $this->requireBorder($id, $world);
		$targetSize = max($targetSize, $border->getMinSize()); // FIXED: minSize clamp
		$this->cancelShrink($border);

		$startSize = $border->getSize();
		$delta = $targetSize - $startSize;
		if($seconds <= 0 && $border->getSpeed() > 0){
			$seconds = abs($delta) / $border->getSpeed(); // use stored speed when no duration is given
		}
		$perTick = $seconds <= 0 ? $delta : ($delta / ($seconds * 20));
		$key = $this->makeKey($world, $id);

		$handler = $this->plugin->getScheduler()->scheduleRepeatingTask(new BorderShrinkTask(
			function(float $newSize) use ($border, $world) : void{
				$border->setSize(max($border->getMinSize(), $newSize)); // FIXED: minSize clamp during shrink
				$this->updateBorderVisual($border, $world);
				$this->handleZeroSize($border, $world);
				$this->saveBorders();
			},
			function() use ($key) : void{
				unset($this->shrinkTasks[$key]);
			},
			$startSize,
			$targetSize,
			$perTick
		), 1);

		$this->shrinkTasks[$key] = $handler;
	}

	public function setBorderLifetime(string $id, World $world, float $seconds) : void{
		$border = $this->requireBorder($id, $world);
		$this->cancelLifetime($border);

		if($seconds <= 0){
			$border->setExpiresAt(null);
			$this->saveBorders();
			return;
		}
		$expiry = time() + (int)$seconds;
		$border->setExpiresAt($expiry);
		$this->saveBorders();

		$key = $this->makeKey($world, $id);
		$this->lifetimeTasks[$key] = $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
			function() use ($id, $world) : void{
				$this->removeBorder($id, $world); // FIXED: lifetime expiry auto-removal
			}
		), (int)($seconds * 20));
	}

	public function setBorderSize(string $id, World $world, float $size) : void{
		$border = $this->requireBorder($id, $world);
		$border->setSize(max($size, $border->getMinSize())); // FIXED: minSize clamp
		$this->saveBorders();
		$this->updateBorderVisual($border, $world);
		$this->handleZeroSize($border, $world);
	}

	public function setBorderCenter(string $id, World $world, Vector3 $center) : void{
		$border = $this->requireBorder($id, $world);
		$border->setCenter($this->snapCenter($center));
		$this->saveBorders();
		$this->updateBorderVisual($border, $world, true);
	}

	public function setBorderMinSize(string $id, World $world, float $minSize) : void{
		$border = $this->requireBorder($id, $world);
		$border->setMinSize($minSize);
		if($border->getSize() < $minSize){
			$border->setSize($minSize); // FIXED: minSize clamp after change
		}
		$this->saveBorders();
		$this->updateBorderVisual($border, $world);
	}

	public function setBorderSpeed(string $id, World $world, float $speed) : void{
		$border = $this->requireBorder($id, $world);
		$border->setSpeed($speed);
		$this->saveBorders();
	}

	public function setBorderSolid(string $id, World $world, bool $solid) : void{
		$border = $this->requireBorder($id, $world);
		$border->setSolid($solid);
		$this->saveBorders();
	}

	public function setDamageConfig(string $id, World $world, float $amount, float $distance, float $delay) : void{
		$border = $this->requireBorder($id, $world);
		$border->setDamageAmount($amount);
		$border->setDamageDistance($distance);
		$border->setDamageDelay($delay);
		$this->saveBorders();
	}

	public function setKnockbackConfig(string $id, World $world, float $power, float $distance, float $delay) : void{
		$border = $this->requireBorder($id, $world);
		$border->setKnockbackPower($power);
		$border->setKnockbackDistance($distance);
		$border->setKnockbackDelay($delay);
		$this->saveBorders();
	}

	public function setOnZeroAction(string $id, World $world, string $action) : void{
		$border = $this->requireBorder($id, $world);
		$border->setOnZeroAction($action);
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
		$worldName = $player->getWorld()->getFolderName();
		foreach($this->borders[$worldName] ?? [] as $border){
			$inside = $this->isInside($border, $to);
			if($inside){
				continue;
			}

			if($border->isSolid() && !$player->hasPermission("visibleborder.bypass")){
				// FIXED: trapped when border shrinks too fast
				$clamped = $this->clampToBorder($border, $to);
				$event->cancel();
				$event->setTo($clamped);
			}

			$this->applyKnockback($player, $border);
			$this->applyDamage($player, $border);
		}
	}

	public function syncPlayerBorders(Player $player) : void{
		$world = $player->getWorld();
		foreach($this->borders[$world->getFolderName()] ?? [] as $border){
			$this->spawnBorderForPlayer($player, $border);
		}
	}

	public function tickPlayer(Player $player) : void{
		if($player->hasPermission("visibleborder.bypass")){
			return;
		}
		$pos = $player->getLocation();
		$worldName = $player->getWorld()->getFolderName();
		foreach($this->borders[$worldName] ?? [] as $border){
			if($this->isInside($border, $pos)){
				continue;
			}
			if($border->isSolid()){
				$clamped = $this->clampToBorder($border, $pos);
				$player->teleport($clamped); // FIXED: trapped when stationary outside after shrink
			}
			$this->applyKnockback($player, $border);
			$this->applyDamage($player, $border);
		}
	}

	public function despawnForPlayer(Player $player) : void{
		$pid = $player->getId();
		if(!isset($this->activeBorders[$pid])){
			return;
		}
		foreach($this->activeBorders[$pid] as $rid){
			$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($rid));
		}
		unset($this->activeBorders[$pid]);
	}

	public function getDefaults() : array{
		return $this->defaults;
	}

	private function handleZeroSize(Border $border, World $world) : void{
		if($border->getSize() > 0){
			return;
		}
		foreach($world->getPlayers() as $player){
			if(!$player->isOnline()){
				continue;
			}
			switch(true){
				case $border->getOnZeroAction() === "kill":
					$player->kill(); // FIXED: size=0 kill action
					break;
				case $border->getOnZeroAction() === "freeze":
					$player->setImmobile(true); // FIXED: size=0 freeze action
					break;
				default:
					if(str_starts_with($border->getOnZeroAction(), "damage")){
						$parts = explode(" ", $border->getOnZeroAction());
						$amount = isset($parts[1]) ? (float)$parts[1] : 1.0;
						$player->setHealth(max(0.0, $player->getHealth() - $amount)); // FIXED: size=0 damage action
					}
					break;
			}
		}
		$this->removeBorder($border->getId(), $world);
	}

	private function applyDamage(Player $player, Border $border) : void{
		$distance = $this->distance2D($player->getLocation(), $border->getCenter());
		if($distance <= $border->getSize() + $border->getDamageDistance()){
			return;
		}
		$pid = $player->getId();
		$key = $this->makeKey($player->getWorld(), $border->getId());
		$now = microtime(true);
		$next = $this->damageCooldowns[$pid][$key] ?? 0.0;
		if($now < $next){
			return; // FIXED: damage delay per player
		}
		$this->damageCooldowns[$pid][$key] = $now + max(0.05, $border->getDamageDelay());
		$player->setHealth(max(0.0, $player->getHealth() - $border->getDamageAmount()));
	}

	private function applyKnockback(Player $player, Border $border) : void{
		$distance = $this->distance2D($player->getLocation(), $border->getCenter());
		if($distance <= $border->getSize() + $border->getKnockbackDistance()){
			return;
		}
		$pid = $player->getId();
		$key = $this->makeKey($player->getWorld(), $border->getId());
		$now = microtime(true);
		$next = $this->knockbackCooldowns[$pid][$key] ?? 0.0;
		if($now < $next){
			return; // FIXED: knockback spam cooldown
		}
		$this->knockbackCooldowns[$pid][$key] = $now + max(0.05, $border->getKnockbackDelay());

		$dirVec = $player->getPosition()->subtractVector($border->getCenter());
		$dir = (new Vector3($dirVec->getX(), 0.0, $dirVec->getZ()))->normalize();
		if($dir->lengthSquared() <= 0){
			$dir = new Vector3(0, 0, 0);
		}
		$player->setMotion($dir->multiply($border->getKnockbackPower()));
	}

	private function clampToBorder(Border $border, Vector3 $pos) : Location{
		$center = $border->getCenter();
		$direction = $pos->subtractVector($center);
		if($direction->lengthSquared() <= 0){
			return Location::fromObject($center, $pos->getWorld(), 0.0, 0.0);
		}
		$direction = $direction->normalize()->multiply(max(0.0, $border->getSize() - 0.1));
		$target = $direction->addVector($center);
		return Location::fromObject(new Vector3($target->getX(), $pos->getY(), $target->getZ()), $pos->getWorld(), 0.0, 0.0);
	}

	private function isInside(Border $border, Vector3 $pos) : bool{
		return $this->distance2D($pos, $border->getCenter()) <= $border->getSize();
	}

	private function distance2D(Vector3 $a, Vector3 $b) : float{
		$dx = $a->getX() - $b->getX();
		$dz = $a->getZ() - $b->getZ();
		return sqrt($dx * $dx + $dz * $dz);
	}

	private function updateBorderVisual(Border $border, World $world, bool $move = false) : void{
		foreach($world->getPlayers() as $player){
			$this->spawnBorderForPlayer($player, $border, $move);
		}
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
		$center = new Vector3($border->getCenter()->getX(), $player->getPosition()->getY(), $border->getCenter()->getZ());
		$rid = $this->activeBorders[$pid][$key] ?? null;

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

		if($rid === null){
			$rid = Entity::nextRuntimeId();
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
		}else{
			$player->getNetworkSession()->sendDataPacket(SetActorDataPacket::create($rid, $meta->getAll(), 0));
			if($forceMove){
				$flags = MoveActorAbsolutePacket::FLAG_GROUND | MoveActorAbsolutePacket::FLAG_TELEPORT;
				$player->getNetworkSession()->sendDataPacket(MoveActorAbsolutePacket::create($rid, $center, 0.0, 0.0, $flags));
			}
		}
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

	private function restoreLifetimes() : void{
		$now = time();
		foreach($this->borders as $worldName => $borders){
			foreach($borders as $id => $border){
				$expires = $border->getExpiresAt();
				if($expires !== null){
					if($expires <= $now){
						$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
						if($world instanceof World){
							$this->removeBorder($id, $world);
						}
						continue;
					}
					$remaining = $expires - $now;
					$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
					if($world instanceof World){
						$this->setBorderLifetime($id, $world, (float)$remaining);
					}
				}
			}
		}
	}

	private function cancelShrink(Border $border) : void{
		$key = $this->makeKeyByBorder($border);
		if(isset($this->shrinkTasks[$key])){
			$this->shrinkTasks[$key]->cancel();
			unset($this->shrinkTasks[$key]);
		}
	}

	private function cancelLifetime(Border $border) : void{
		$key = $this->makeKeyByBorder($border);
		if(isset($this->lifetimeTasks[$key])){
			$this->lifetimeTasks[$key]->cancel();
			unset($this->lifetimeTasks[$key]);
		}
	}

	private function makeKey(World $world, string $id) : string{
		return $world->getFolderName() . ":" . $id;
	}

	private function makeKeyByBorder(Border $border) : string{
		return $border->getWorldName() . ":" . $border->getId();
	}

	private function requireBorder(string $id, World $world) : Border{
		$border = $this->getBorder($id, $world);
		if($border === null){
			throw new RuntimeException("Border '{$id}' does not exist in world '{$world->getFolderName()}'.");
		}
		return $border;
	}

	private function getDefaultFloat(string $path) : float{
		$value = $this->getNestedDefault($path);
		return (float)$value;
	}

	private function getDefaultBool(string $path) : bool{
		$value = $this->getNestedDefault($path);
		return (bool)$value;
	}

	private function getNestedDefault(string $path){
		$parts = explode(".", $path);
		$cursor = $this->defaults;
		foreach($parts as $part){
			if(!isset($cursor[$part])){
				return null;
			}
			$cursor = $cursor[$part];
		}
		return $cursor;
	}

	private function snapCenter(Vector3 $center) : Vector3{
		return new Vector3(floor($center->getX()) + 0.5, $center->getY(), floor($center->getZ()) + 0.5);
	}
}
