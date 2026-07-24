<?php

declare(strict_types=1);

final class SafetyEngine
{
    private int $instanceID;
    private float $maxRuntime;
    private float $lastStartTime = 0.0;
    private bool $running = false;

    public function __construct(int $instanceID, float $maxRuntime) 
    {
        $this->instanceID = $instanceID;
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
        return $this->lastStartTime; 
    }

    public function IsTimeout(): bool
    {
        if (!$this->running || $this->lastStartTime <= 0.0) return false;
        return (microtime(true) - $this->lastStartTime) > $this->maxRuntime;
    }
}
