<?php

declare(strict_types=1);

final class SafetyEngine
{
    private IPSModule $module;
    private float $maxRuntime;
    private float $lastStartTime = 0.0;
    private bool $running = false;

    public function __construct(IPSModule $module, float $maxRuntime) 
    {
        $this->module = $module;
        $this->maxRuntime = ($maxRuntime > 0.0) ? $maxRuntime : 300.0; 
    }

    public function Rehydrate(float $lastStartTime, bool $running): void
    {
        $this->lastStartTime = $lastStartTime;
        $this->running = $running;
    }

    public function Start(): float
    {
        $this->lastStartTime = microtime(true);
        $this->running = true;

        $this->module->SendDebug('SafetyEngine', 'Fahrüberwachung gestartet', 0);
        return $this->lastStartTime; 
    }

    public function Stop(): void
    {
        $this->running = false;
        $this->module->SendDebug('SafetyEngine', 'Fahrüberwachung beendet', 0);
    }

    public function IsTimeout(): bool
    {
        if (!$this->running || $this->lastStartTime <= 0.0) {
            return false;
        }

        $runtime = microtime(true) - $this->lastStartTime;

        if ($runtime > $this->maxRuntime) {
            $this->module->SendDebug('SafetyEngine', sprintf('TIMEOUT: Maximale Fahrzeit überschritten (%.1fs > %.1fs)', $runtime, $this->maxRuntime), 0);
            return true;
        }

        return false;
    }

    public function ValidateRuntime(float $runtime): bool
    {
        return ($runtime >= 0.1 && $runtime <= 600.0);
    }

    public function ValidateRelayCombination(int $up, int $down): bool 
    {
        if ($up <= 0 || $down <= 0) {
            $this->module->SendDebug('SafetyEngine', 'Validierungsfehler: Instanz-IDs fehlen.', 0);
            return false;
        }

        if ($up === $down) {
            $this->module->SendDebug('SafetyEngine', 'Validierungsfehler: Relais für AUF und AB sind identisch!', 0);
            return false;
        }

        return true;
    }

    public function SafeStop(RelayEngine $relay): void
    {
        $this->running = false;
        $relay->Stop(); 
        $this->module->SendDebug('SafetyEngine', 'Sicherheitsstopp ausgeführt', 0);
    }
}
