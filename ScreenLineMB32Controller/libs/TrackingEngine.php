<?php

declare(strict_types=1);

final class TrackingEngine
{
    private IPSModule $module;
    private float $runtimeUp;
    private float $runtimeDown;
    private float $position = 0.0;
    private float $lastTimestamp = 0.0;

    public const DIRECTION_UP = 1;
    public const DIRECTION_DOWN = 2;

    public function __construct(IPSModule $module, float $runtimeUp, float $runtimeDown) 
    {
        $this->module = $module;
        $this->runtimeUp = max(0.1, $runtimeUp);
        $this->runtimeDown = max(0.1, $runtimeDown);
    }

    public function Rehydrate(float $position, float $lastTimestamp): void
    {
        $this->position = $this->Clamp($position);
        $this->lastTimestamp = $lastTimestamp;
    }

    public function SetPosition(float $position): void
    {
        $this->position = $this->Clamp($position);
    }

    public function GetPosition(): float
    {
        return $this->position;
    }

    public function Move(int $direction): void 
    {
        if ($this->lastTimestamp <= 0.0) {
            $this->lastTimestamp = microtime(true);
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->lastTimestamp;
        $this->lastTimestamp = $now;

        switch ($direction) {
            case self::DIRECTION_UP:
                $this->position -= ($elapsed / $this->runtimeUp) * 100.0;
                break;

            case self::DIRECTION_DOWN:
                $this->position += ($elapsed / $this->runtimeDown) * 100.0;
                break;
        }

        $this->position = $this->Clamp($this->position);

        $this->module->SendDebug(
            'TrackingEngine',
            sprintf('Delta=%.3fs | Position=%.2f %%', $elapsed, $this->position),
            0
        );
    }

    public function EstimateRuntime(float $target): float 
    {
        $distance = abs($target - $this->position);

        if ($target > $this->position) {
            return ($distance / 100.0) * $this->runtimeDown;
        }

        return ($distance / 100.0) * $this->runtimeUp;
    }

    public function IsAtTarget(float $target, float $tolerance = 0.5): bool 
    {
        return abs($this->position - $target) <= $tolerance;
    }

    public function ReferenceClosed(): void
    {
        $this->position = 0.0;
        $this->module->SendDebug('TrackingEngine', 'Referenz geschlossen gespeichert', 0);
    }

    private function Clamp(float $value): float 
    {
        return min(100.0, max(0.0, $value));
    }
}
