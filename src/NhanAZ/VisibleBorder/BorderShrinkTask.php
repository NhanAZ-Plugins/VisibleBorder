<?php

declare(strict_types=1);

namespace NhanAZ\VisibleBorder;

use pocketmine\scheduler\Task;

/**
 * Reusable shrink/expand task using a per-tick callback.
 */
final class BorderShrinkTask extends Task {
	/**
	 * @param callable(float $newSize) : void $update
	 * @param callable() : void $onComplete
	 */
	public function __construct(
		private $update,
		private $onComplete,
		private float $currentSize,
		private float $targetSize,
		private float $perTick
	){}

	public function onRun() : void{
		if(abs($this->currentSize - $this->targetSize) <= abs($this->perTick)){
			($this->update)($this->targetSize);
			($this->onComplete)();
			$this->getHandler()?->cancel();
			return;
		}
		$this->currentSize += $this->perTick;
		($this->update)($this->currentSize);
	}
}
