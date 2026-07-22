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

        switch ($currentStep) {
            case 1:
                return 0.0;
            case 2:
                return 100.0;
            case 3:
                return $finalTarget;
            default:
                return null;
        }
    }
}
