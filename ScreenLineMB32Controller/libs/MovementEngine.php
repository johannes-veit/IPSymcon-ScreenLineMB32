<?php

declare(strict_types=1);

final class MovementEngine
{
    private IPSModule $module;
    private RelayEngine $relay;
    private float $startPosition = 0.0;
    private float $targetPosition = 0.0;
    private float $runtime = 0.0;
    private float $travelDistance = 0.0;
    private bool $running = false;
    private bool $movingDown = false;

    public function __construct(IPSModule $module, RelayEngine $relay) 
    {
        $this->module = $module;
        $this->relay = $relay;
    }

    public function Start(float $current, float $target, float $runtime): bool 
    {
        if ($current === $target) {
            return false;
        }

        $this->startPosition = $current;
        $this->targetPosition = $target;
        $this->runtime = max(0.1, $runtime);
        $this->travelDistance = abs($target - $current);
        $this->movingDown = ($target > $current);

        if ($this->movingDown) {
            if (!$this->relay->MoveDown()) {
                return false;
            }
        } else {
            if (!$this->relay->MoveUp()) {
                return false;
            }
        }

        $this->running = true;

        $this->module->SendDebug(
            'MovementEngine',
            sprintf('Start %.1f -> %.1f (%.1fs)', $current, $target, $runtime),
            0
        );

        return true;
    }

    public function Stop(): void
    {
        $this->relay->Stop();
        $this->running = false;
        $this->module->SendDebug('MovementEngine', 'Bewegung beendet', 0);
    }

    public function IsRunning(): bool
    {
        return $this->running;
    }

    public function IsMovingDown(): bool
    {
        return $this->movingDown;
    }

    public function GetRuntime(): float
    {
        return $this->runtime;
    }

    public function GetTarget(): float
    {
        return $this->targetPosition;
    }

    public function GetStart(): float
    {
        return $this->startPosition;
    }
}
