<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\api;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\ZoneRuleManager;
use NhanAZ\VisibleBorder\model\Border;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;

final class VisibleBorderAPI {
	private static ?self $instance = null;

	private function __construct(
		private BorderManager $manager,
		private ZoneRuleManager $rules
	){
	}

	public static function init(BorderManager $manager, ZoneRuleManager $rules) : void{
		self::$instance = new self($manager, $rules);
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
	 * Shrink or expand the border to a target size over the provided seconds.
	 */
	public function shrinkBorder(string $id, World $world, float $targetSize, float $seconds) : void{
		$this->manager->shrinkBorder($id, $world, $targetSize, $seconds);
	}

	/**
	 * Set a lifetime after which the border will be removed automatically.
	 */
	public function setBorderLifetime(string $id, World $world, float $seconds) : void{
		$this->manager->setBorderLifetime($id, $world, $seconds);
	}

	/**
	 * Check if a player is inside the specified border.
	 */
	public function isInsideBorder(Player $player, string $id) : bool{
		return $this->manager->isInsideBorder($player, $id);
	}

	/**
	 * Set a zone rule value for a border.
	 */
	public function setRule(string $borderId, World $world, string $rule, mixed $value) : void{
		$this->rules->setRule($borderId, $world, $rule, $value);
	}

	/**
	 * Get a rule value (null if not set).
	 */
	public function getRule(string $borderId, World $world, string $rule) : mixed{
		return $this->rules->getRule($borderId, $world, $rule);
	}

	/**
	 * Reset all rules for a border.
	 */
	public function resetRules(string $borderId, World $world) : void{
		$this->rules->resetRules($borderId, $world);
	}

	/**
	 * Apply a named preset to a border.
	 */
	public function applyPreset(string $borderId, World $world, string $preset) : void{
		$this->rules->applyPreset($borderId, $world, $preset);
	}

	/**
	 * Get all rule data for a border.
	 */
	public function getRulesForBorder(string $borderId, World $world) : array{
		return $this->rules->getRulesForBorder($borderId, $world);
	}
}
