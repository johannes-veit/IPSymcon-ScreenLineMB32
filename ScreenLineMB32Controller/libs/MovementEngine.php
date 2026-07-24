<?php

declare(strict_types=1);

final class MovementEngine
{
    private int $instanceID;
    private RelayEngine $relay;

    public function __construct(int $instanceID, RelayEngine $relay) 
    {
        $this->instanceID = $instanceID;
        $this->relay = $relay;
    }

    public function Start(float $current, float $target, float $runtime): bool 
    {
        if ($current === $target && $target !== 100.0 && $target !== 0.0) return false;

        $movingDown = ($target > $current || ($current === 100.0 && $target === 100.0));
        return $movingDown ? $this->relay->MoveDown() : $this->relay->MoveUp();
    }

    public function Stop(): void
    {
        $this->relay->Stop();
    }
}
