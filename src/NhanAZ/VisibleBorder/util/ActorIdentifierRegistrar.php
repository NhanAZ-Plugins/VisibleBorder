<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\util;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\world\World;
use ReflectionClass;

final class ActorIdentifierRegistrar {
	/**
	 * Registers the class with EntityFactory and injects the identifier into AvailableActorIdentifiers,
	 * so the client accepts AddActorPacket for this custom entity ID without requiring the Customies plugin.
	 * @phpstan-param class-string<Entity> $className
	 */
	public static function register(string $className, string $identifier) : void{
		// Register with PMMP's factory (spawn/save safety)
		EntityFactory::getInstance()->register(
			$className,
			static function(World $world, CompoundTag $nbt) use ($className) : Entity{
				return new $className(EntityDataHelper::parseLocation($nbt, $world), $nbt);
			},
			[$identifier]
		);

		// Inject into AvailableActorIdentifiers packet cache
		$cache = StaticPacketCache::getInstance();
		$ref = new ReflectionClass($cache);
		$prop = $ref->getProperty("availableActorIdentifiers");
		/** @var AvailableActorIdentifiersPacket $packet */
		$packet = $prop->getValue($cache);
		$root = $packet->identifiers->getRoot();

		/** @var ListTag<CompoundTag>|null $list */
		$list = $root->getListTag("idlist");
		if($list === null){
			$list = new ListTag();
			$root->setTag("idlist", $list);
		}
		$list->push(CompoundTag::create()
			->setString("id", $identifier)
			->setString("bid", ""));

		$packet->identifiers = new CacheableNbt($root);
	}
}
