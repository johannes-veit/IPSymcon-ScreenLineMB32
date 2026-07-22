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
        if ($current === $target && $target !== 100.0 && $target !== 0.0) {
            return false;
        }

        $this->startPosition = $current;
        $this->targetPosition = $target;
        $this->runtime = max(0.1, $runtime);
        $this->travelDistance = abs($target - $current);
        $this->movingDown = ($target > $current || ($current === 100.0 && $target === 100.0));

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

        IPS_SendDebug(
            $this->module->GetModuleInstanceID(),
            'MovementEngine',
            sprintf('Start %.1f -> %.1f (%.1fs inkl. Trägheiten/Nachlauf)', $current, $target, $runtime),
            0
        );

        return true;
    }

    public function Stop(): void
    {
        $this->relay->Stop();
        $this->running = false;
        IPS_SendDebug($this->module->GetModuleInstanceID(), 'MovementEngine', 'Bewegung beendet', 0);
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
