<?php

declare(strict_types=1);

final class ShakeFree
{
    private IPSModule $module;
    private float $duration;
    private bool $enabled;

    public function __construct(IPSModule $module, bool $enabled, float $duration) 
    {
        $this->module = $module;
        $this->enabled = $enabled;
        $this->duration = $duration;
    }

    public function IsEnabled(): bool
    {
        return $this->enabled;
    }

    public function GetDuration(): float
    {
        return $this->duration;
    }

    public function Validate(): bool
    {
        if (!$this->enabled) {
            return true;
        }
        return ($this->duration > 0.0 && $this->duration <= 10.0);
    }

    public function GetNextSequenceTarget(int $currentStep, float $finalTarget): ?float
    {
        if (!$this->enabled) {
            return null;
        }

        return match ($currentStep) {
            1 => 0.0,
            2 => 100.0,
            3 => $finalTarget,
            default => null,
        };
    }
}
