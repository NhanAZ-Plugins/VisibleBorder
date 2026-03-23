<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder\model;

use pocketmine\math\Vector3;

final class Border {
	public function __construct(
		private string $id,
		private string $worldName,
		private float $size,
		private float $minSize,
		private float $speed,
		private Vector3 $center,
		private bool $solid,
		private float $damageAmount,
		private float $damageDistance,
		private float $damageDelay,
		private float $knockbackPower,
		private float $knockbackDistance,
		private float $knockbackDelay,
		private string $onZeroAction,
		private array $rules = [],
		private ?int $expiresAt = null
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

	public function getMinSize() : float{
		return $this->minSize;
	}

	public function setMinSize(float $minSize) : void{
		$this->minSize = $minSize;
	}

	public function getSpeed() : float{
		return $this->speed;
	}

	public function setSpeed(float $speed) : void{
		$this->speed = $speed;
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

	public function getDamageAmount() : float{
		return $this->damageAmount;
	}

	public function setDamageAmount(float $amount) : void{
		$this->damageAmount = $amount;
	}

	public function getDamageDistance() : float{
		return $this->damageDistance;
	}

	public function setDamageDistance(float $distance) : void{
		$this->damageDistance = $distance;
	}

	public function getDamageDelay() : float{
		return $this->damageDelay;
	}

	public function setDamageDelay(float $delay) : void{
		$this->damageDelay = $delay;
	}

	public function getKnockbackPower() : float{
		return $this->knockbackPower;
	}

	public function setKnockbackPower(float $power) : void{
		$this->knockbackPower = $power;
	}

	public function getKnockbackDistance() : float{
		return $this->knockbackDistance;
	}

	public function setKnockbackDistance(float $distance) : void{
		$this->knockbackDistance = $distance;
	}

	public function getKnockbackDelay() : float{
		return $this->knockbackDelay;
	}

	public function setKnockbackDelay(float $delay) : void{
		$this->knockbackDelay = $delay;
	}

	public function getOnZeroAction() : string{
		return $this->onZeroAction;
	}

	public function setOnZeroAction(string $action) : void{
		$this->onZeroAction = $action;
	}

	public function getRules() : array{
		return $this->rules;
	}

	public function setRules(array $rules) : void{
		$this->rules = $rules;
	}

	public function setRule(string $key, mixed $value) : void{
		$this->rules[$key] = $value;
	}

	public function getExpiresAt() : ?int{
		return $this->expiresAt;
	}

	public function setExpiresAt(?int $timestamp) : void{
		$this->expiresAt = $timestamp;
	}

	public function toArray() : array{
		return [
			"size" => $this->size,
			"min_size" => $this->minSize,
			"speed" => $this->speed,
			"center" => [
				"x" => $this->center->getX(),
				"y" => $this->center->getY(),
				"z" => $this->center->getZ()
			],
			"solid" => $this->solid,
			"damage" => [
				"amount" => $this->damageAmount,
				"distance" => $this->damageDistance,
				"delay" => $this->damageDelay
			],
			"knockback" => [
				"power" => $this->knockbackPower,
				"distance" => $this->knockbackDistance,
				"delay" => $this->knockbackDelay
			],
			"on_zero" => $this->onZeroAction,
			"rules" => $this->rules,
			"expires_at" => $this->expiresAt
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
			(float)($data["min_size"] ?? 1.0),
			(float)($data["speed"] ?? 0.0),
			$center,
			(bool)($data["solid"] ?? true),
			(float)($data["damage"]["amount"] ?? 0.0),
			(float)($data["damage"]["distance"] ?? 1.0),
			(float)($data["damage"]["delay"] ?? 1.0),
			(float)($data["knockback"]["power"] ?? 0.0),
			(float)($data["knockback"]["distance"] ?? 1.0),
			(float)($data["knockback"]["delay"] ?? 1.0),
			(string)($data["on_zero"] ?? "kill"),
			(array)($data["rules"] ?? []),
			isset($data["expires_at"]) ? (int)$data["expires_at"] : null
		);
	}
}
