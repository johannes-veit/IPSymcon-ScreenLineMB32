<?php

declare(strict_types=1);

final class SafetyEngine
{
    private IPSModule $module;

    private float $maxRuntime;

    private float $lastStartTime = 0.0;

    private bool $running = false;


    public function __construct(
        IPSModule $module,
        float $maxRuntime
    ) {
        $this->module = $module;
        $this->maxRuntime = $maxRuntime;
    }


    public function Start(): void
    {
        $this->lastStartTime = microtime(true);
        $this->running = true;

        $this->module->SendDebug(
            'SafetyEngine',
            'Fahrüberwachung gestartet',
            0
        );
    }


    public function Stop(): void
    {
        $this->running = false;

        $this->module->SendDebug(
            'SafetyEngine',
            'Fahrüberwachung beendet',
            0
        );
    }


    public function IsTimeout(): bool
    {
        if (!$this->running) {
            return false;
        }


        $runtime = microtime(true) - $this->lastStartTime;


        if ($runtime > $this->maxRuntime) {

            $this->module->SendDebug(
                'SafetyEngine',
                'Maximale Fahrzeit überschritten',
                0
            );

            return true;
        }


        return false;
    }


    public function ValidateRuntime(float $runtime): bool
    {
        if ($runtime < 1) {
            return false;
        }


        if ($runtime > 600) {
            return false;
        }


        return true;
    }


    public function ValidateRelayCombination(
        int $up,
        int $down
    ): bool {

        if ($up <= 0 || $down <= 0) {
            return false;
        }


        if ($up === $down) {
            return false;
        }


        return true;
    }


    public function SafeStop(): void
    {
        $this->running = false;


        $this->module->SendDebug(
            'SafetyEngine',
            'Sicherheitsstopp ausgeführt',
            0
        );
    }
}