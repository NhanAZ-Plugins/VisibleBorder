<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\api;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\model\Border;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;

final class VisibleBorderAPI {
	private static ?self $instance = null;

	private function __construct(private BorderManager $manager){
	}

	public static function init(BorderManager $manager) : void{
		self::$instance = new self($manager);
	}

	public static function get() : self{
		if(self::$instance === null){
			throw new RuntimeException("VisibleBorderAPI has not been initialized.");
		}
		return self::$instance;
	}

	/**
	 * Create a new border in the given world.
	 */
	public function createBorder(string $id, World $world, float $size, Vector3 $center) : Border{
		return $this->manager->createBorder($id, $world, $size, $center);
	}

	/**
	 * Remove the border by ID in the given world.
	 */
	public function removeBorder(string $id, World $world) : bool{
		if(!$this->manager->removeBorder($id, $world)){
			throw new RuntimeException("Border '{$id}' does not exist in world '{$world->getFolderName()}'.");
		}
		return true;
	}

	/**
	 * Get a border instance or null when it does not exist.
	 */
	public function getBorder(string $id, World $world) : ?Border{
		return $this->manager->getBorder($id, $world);
	}

	/**
	 * Get all borders in a world.
	 *
	 * @return Border[]
	 */
	public function getBordersInWorld(World $world) : array{
		return $this->manager->getBordersInWorld($world);
	}

	/**
	 * Set the size of a border immediately.
	 */
	public function setBorderSize(string $id, World $world, float $size) : void{
		$this->manager->setBorderSize($id, $world, $size);
	}

	/**
	 * Set the center of a border.
	 */
	public function setBorderCenter(string $id, World $world, Vector3 $center) : void{
		$this->manager->setBorderCenter($id, $world, $center);
	}

	/**
	 * Set whether the border blocks movement.
	 */
	public function setBorderSolid(string $id, World $world, bool $solid) : void{
		$this->manager->setBorderSolid($id, $world, $solid);
	}

	/**
	 * Check if a player is inside the specified border.
	 */
	public function isInsideBorder(Player $player, string $id) : bool{
		return $this->manager->isInsideBorder($player, $id);
	}
}
