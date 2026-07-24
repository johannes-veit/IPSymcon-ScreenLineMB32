<?php

declare(strict_types=1);

final class ShakeFree
{
    private float $duration;
    private bool $enabled;

    public function __construct(bool $enabled, float $duration) 
    {
        $this->enabled = $enabled;
        $this->duration = $duration;
    }

    public function IsEnabled(): bool
    {
        return $this->enabled;
    }

    public function GetNextSequenceTarget(int $currentStep, float $finalTarget): ?float
    {
        if (!$this->enabled) return null;
        return ($currentStep === 1) ? 0.0 : (($currentStep === 2) ? 100.0 : (($currentStep === 3) ? $finalTarget : null));
    }
}
