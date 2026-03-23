<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\model;

use pocketmine\math\Vector3;

final class Border {
	public function __construct(
		private string $id,
		private string $worldName,
		private float $size,
		private Vector3 $center,
		private bool $solid
	){}

	public function getId() : string{
		return $this->id;
	}

	public function getWorldName() : string{
		return $this->worldName;
	}

	public function getSize() : float{
		return $this->size;
	}

	public function setSize(float $size) : void{
		$this->size = $size;
	}

	public function getCenter() : Vector3{
		return $this->center;
	}

	public function setCenter(Vector3 $center) : void{
		$this->center = $center;
	}

	public function isSolid() : bool{
		return $this->solid;
	}

	public function setSolid(bool $solid) : void{
		$this->solid = $solid;
	}

	public function toArray() : array{
		return [
			"size" => $this->size,
			"center" => [
				"x" => $this->center->getX(),
				"y" => $this->center->getY(),
				"z" => $this->center->getZ()
			],
			"solid" => $this->solid
		];
	}

	public static function fromArray(string $id, string $worldName, array $data) : self{
		$center = new Vector3(
			(float)($data["center"]["x"] ?? 0.5),
			(float)($data["center"]["y"] ?? 0.0),
			(float)($data["center"]["z"] ?? 0.5)
		);

		return new self(
			$id,
			$worldName,
			(float)($data["size"] ?? 0),
			$center,
			(bool)($data["solid"] ?? true)
		);
	}
}
