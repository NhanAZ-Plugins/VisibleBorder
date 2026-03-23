<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\api;

use NhanAZ\VisibleBorder\BorderManager;
use NhanAZ\VisibleBorder\Main;
use pocketmine\math\Vector2;
use pocketmine\player\Player;
use pocketmine\world\Position;

class VisibleBorderAPI {
	private static ?self $instance = null;

	private function __construct(private Main $plugin, private BorderManager $manager){
	}

	public static function init(Main $plugin, BorderManager $manager) : void{
		self::$instance = new self($plugin, $manager);
	}

	public static function get() : self{
		return self::$instance;
	}

	public function setWorldBorder(string $world, Vector2 $center, float $radius) : void{
		$this->manager->setBorder($world, $center, $radius);
	}

	public function removeWorldBorder(string $world) : void{
		$this->manager->setBorder($world, new Vector2(0, 0), 0.0);
	}

	public function showBorder(Player $player) : void{
		$this->manager->toggleHidden($player, false);
	}

	public function hideBorder(Player $player) : void{
		$this->manager->toggleHidden($player, true);
	}

	public function shrinkBorder(string $world, float $newRadius) : void{
		$this->manager->setBorder($world, new Vector2(0, 0), $newRadius);
	}

	public function isInside(Position $pos) : bool{
		return $this->manager->isInside($pos->getWorld(), $pos);
	}
}
