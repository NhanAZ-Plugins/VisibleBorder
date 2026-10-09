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

	/** @return array{size: float, center: array{x: float, y: float, z: float}, solid: bool} */
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

	/** @param array<string, mixed> $data */
	public static function fromArray(string $id, string $worldName, array $data) : self{
		$centerData = $data["center"] ?? [];
		if(!is_array($centerData)){
			$centerData = [];
		}
		$coordinate = static fn(mixed $value, float $default) : float => is_numeric($value) ? (float)$value : $default;
		$center = new Vector3(
			$coordinate($centerData["x"] ?? null, 0.5),
			$coordinate($centerData["y"] ?? null, 0.0),
			$coordinate($centerData["z"] ?? null, 0.5)
		);

		return new self(
			$id,
			$worldName,
			$coordinate($data["size"] ?? null, 0.0),
			$center,
			(bool)($data["solid"] ?? true)
		);
	}
}
