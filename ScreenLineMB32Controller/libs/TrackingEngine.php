<?php

declare(strict_types=1);

final class TrackingEngine
{
    private IPSModule $module;
    private float $runtimeUp;
    private float $runtimeDown;
    private float $remainingSlatTime = 0.0;
    private float $remainingSoftStartTime = 0.0;
    private float $position = 0.0;
    private float $slatPosition = 0.0;
    private float $startPositionForAutoRef = -1.0; 
    private float $lastTimestamp = 0.0;

    public const DIRECTION_UP = 1;
    public const DIRECTION_DOWN = 2;

    public function __construct(IPSModule $module, float $runtimeUp, float $runtimeDown, float $slatTime = 0.0, float $softTime = 0.0) 
    {
        $this->module = $module;
        $this->runtimeUp = max(0.1, $runtimeUp);
        $this->runtimeDown = max(0.1, $runtimeDown);
        $this->remainingSlatTime = max(0.0, $slatTime);
        $this->remainingSoftStartTime = max(0.0, $softTime);
    }

    public function Rehydrate(float $position, float $slatPosition, float $lastTimestamp, float $remSlat, float $remSoft): void
    {
        $this->position = $this->Clamp($position);
        $this->slatPosition = $this->Clamp($slatPosition);
        $this->lastTimestamp = $lastTimestamp;
        $this->remainingSlatTime = max(0.0, $remSlat);
        $this->remainingSoftStartTime = max(0.0, $remSoft);
    }

    public function GetPosition(): float
    {
        return $this->position;
    }

    public function GetSlatPosition(): float
    {
        return $this->slatPosition;
    }

    public function GetRemainingSlatTime(): float
    {
        return $this->remainingSlatTime;
    }

    public function GetRemainingSoftStartTime(): float
    {
        return $this->remainingSoftStartTime;
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

        if ($this->remainingSoftStartTime > 0.0) {
            if ($elapsed >= $this->remainingSoftStartTime) {
                $elapsed -= $this->remainingSoftStartTime;
                $this->remainingSoftStartTime = 0.0;
            } else {
                $this->remainingSoftStartTime -= $elapsed;
                return; 
            }
        }

        if ($this->remainingSlatTime > 0.0) {
            $allocatedTime = min($elapsed, $this->remainingSlatTime);
            $this->remainingSlatTime -= $allocatedTime;
            $elapsed -= $allocatedTime;

            $totalSlatTime = max(0.1, (float)$this->module->ReadPropertyFloat('SlatTurnTime'));
            $slatDelta = ($allocatedTime / $totalSlatTime) * 100.0;

            if ($direction === self::DIRECTION_DOWN) {
                $this->slatPosition += $slatDelta;
            } else {
                $this->slatPosition -= $slatDelta;
            }
            $this->slatPosition = $this->Clamp($this->slatPosition);
        }

        if ($elapsed > 0.0) {
            if ($this->startPositionForAutoRef < 0.0) {
                $this->startPositionForAutoRef = $this->position;
            }

            switch ($direction) {
                case self::DIRECTION_UP:
                    $this->position -= ($elapsed / $this->runtimeUp) * 100.0;
                    if (abs($this->startPositionForAutoRef - $this->position) >= 5.0) {
                        $this->slatPosition = 0.0; 
                    }
                    break;

                case self::DIRECTION_DOWN:
                    $this->position += ($elapsed / $this->runtimeDown) * 100.0;
                    if (abs($this->startPositionForAutoRef - $this->position) >= 5.0) {
                        $this->slatPosition = 100.0; 
                    }
                    break;
            }
            $this->position = $this->Clamp($this->position);
        }

        IPS_SendDebug(
            $this->module->GetModuleInstanceID(),
            'TrackingEngine',
            sprintf('Fahrt aktiv | Pos=%.1f%% | Lamelle=%.1f%%', $this->position, $this->slatPosition),
            0
        );
    }

    public function EstimateRuntime(float $target): float 
    {
        $distance = abs($target - $this->position);
        $baseRuntime = ($target > $this->position) 
            ? (($distance / 100.0) * $this->runtimeDown) 
            : (($distance / 100.0) * $this->runtimeUp);

        return $baseRuntime + $this->remainingSlatTime + $this->remainingSoftStartTime;
    }

    public function IsAtTarget(float $target, float $tolerance = 0.5): bool 
    {
        return abs($this->position - $target) <= $tolerance;
    }

    public function ReferenceClosed(): void
    {
        $this->position = 100.0;
        $this->slatPosition = 100.0;
    }

    private function Clamp(float $value): float 
    {
        return min(100.0, max(0.0, $value));
    }
}
