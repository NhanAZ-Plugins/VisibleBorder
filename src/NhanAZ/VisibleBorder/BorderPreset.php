<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use pocketmine\utils\Config;

final class BorderPreset {
	/** @var array<string,array> */
	private array $presets = [];

	public function __construct(string $path){
		$config = new Config($path, Config::YAML);
		$this->presets = $config->getAll();
	}

	public function getPreset(string $name) : ?array{
		return $this->presets[$name] ?? null;
	}
}
