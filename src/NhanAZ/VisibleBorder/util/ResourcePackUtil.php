<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\util;

use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use Symfony\Component\Filesystem\Path;
use ZipArchive;
use function count;
use function file_get_contents;
use function str_starts_with;
use function strlen;
use function substr;
use function unlink;

final class ResourcePackUtil {

	private const PACK_FOLDER = "VisibleBorder Pack";

	/**
	 * Builds the .mcpack ZIP file from the plugin's embedded resources,
	 * then logs each entry so you can verify the ZIP structure in the console.
	 */
	public static function compileAndSave(PluginBase $plugin) : string {
		$zipPath = Path::join($plugin->getDataFolder(), $plugin->getName() . '.mcpack');
		@unlink($zipPath);

		$zip = new ZipArchive();
		$openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if($openResult !== true){
			throw new \RuntimeException("ZipArchive::open() failed with code: $openResult");
		}

		$prefix = self::PACK_FOLDER . "/";
		$entries = [];

		foreach($plugin->getResources() as $resourceKey => $resource){
			if(!str_starts_with($resourceKey, $prefix)){
				continue;
			}

			// Strip "VisibleBorder Pack/" → in-pack path with forward slashes
			$inPackPath = substr($resourceKey, strlen($prefix));

			$content = file_get_contents($resource->getPathname());
			if($content === false){
				$plugin->getLogger()->warning("[ResourcePackUtil] Could not read: $resourceKey");
				continue;
			}

			$zip->addFromString($inPackPath, $content);
			$entries[] = $inPackPath;
		}

		$zip->close();

		// Log every entry so we can verify the ZIP structure in the console
		$plugin->getLogger()->info("[ResourcePackUtil] ZIP entries (" . count($entries) . "):");
		foreach($entries as $entry){
			$plugin->getLogger()->info("[ResourcePackUtil]   → $entry");
		}

		return $zipPath;
	}

	/**
	 * Registers the compiled pack with PMMP's ResourcePackManager (public API, no reflection).
	 */
	public static function register(PluginBase $plugin) : void {
		$zipPath = Path::join($plugin->getDataFolder(), $plugin->getName() . '.mcpack');

		try {
			$pack = new ZippedResourcePack($zipPath);
		} catch(\Exception $e){
			$plugin->getLogger()->error("[ResourcePackUtil] Failed to load pack: " . $e->getMessage());
			return;
		}

		$plugin->getLogger()->info("[ResourcePackUtil] Pack UUID=" . $pack->getPackId() . " v" . $pack->getPackVersion());

		$manager = $plugin->getServer()->getResourcePackManager();
		$stack = $manager->getResourceStack();
		array_unshift($stack, $pack);
		$manager->setResourceStack($stack);
		$manager->setResourcePacksRequired(true);

		$plugin->getLogger()->info(
			"[ResourcePackUtil] Registered. Stack=" . count($manager->getResourceStack()) . " pack(s), force=true"
		);
	}

	/**
	 * Removes our pack from the stack on plugin disable.
	 */
	public static function unregister(PluginBase $plugin) : void {
		$manager = $plugin->getServer()->getResourcePackManager();
		$stack = $manager->getResourceStack();
		$zipPath = Path::join($plugin->getDataFolder(), $plugin->getName() . '.mcpack');

		foreach($stack as $key => $pack){
			if($pack instanceof ZippedResourcePack && $pack->getPath() === $zipPath){
				unset($stack[$key]);
				break;
			}
		}

		$manager->setResourceStack(array_values($stack));
		@unlink($zipPath);
		$plugin->getLogger()->info("[ResourcePackUtil] Unregistered pack.");
	}
}
