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

        switch ($currentStep) {
            case 1:
                IPS_SendDebug($this->module->GetModuleInstanceID(), 'ShakeFree', 'Schritt 1: Entlastungsfahrt auf 0% vorbereitet', 0);
                return 0.0;
            case 2:
                IPS_SendDebug($this->module->GetModuleInstanceID(), 'ShakeFree', 'Schritt 2: Entlastungsfahrt auf 100% vorbereitet', 0);
                return 100.0;
            case 3:
                IPS_SendDebug($this->module->GetModuleInstanceID(), 'ShakeFree', 'Schritt 3: Fahrt auf Endziel vorbereitet: ' . $finalTarget, 0);
                return $finalTarget;
            default:
                return null;
        }
    }
}
