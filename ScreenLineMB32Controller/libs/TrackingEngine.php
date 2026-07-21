<?php

declare(strict_types=1);

final class TrackingEngine
{
    private IPSModule $module;

    private float $runtimeUp;

    private float $runtimeDown;

    private float $position = 0.0;

    private float $lastTimestamp = 0.0;


    public function __construct(
        IPSModule $module,
        float $runtimeUp,
        float $runtimeDown
    ) {
        $this->module = $module;

        $this->runtimeUp = $runtimeUp;
        $this->runtimeDown = $runtimeDown;

        $this->lastTimestamp = microtime(true);
    }


    public function SetPosition(float $position): void
    {
        $this->position = $this->Clamp($position);
    }


    public function GetPosition(): float
    {
        return $this->position;
    }


    public function Move(
        int $direction
    ): void {

        $now = microtime(true);

        $elapsed = $now - $this->lastTimestamp;

        $this->lastTimestamp = $now;


        switch ($direction) {

            case Direction::Up:

                $this->position -= (
                    $elapsed / $this->runtimeUp
                ) * 100;

                break;


            case Direction::Down:

                $this->position += (
                    $elapsed / $this->runtimeDown
                ) * 100;

                break;
        }


        $this->position = $this->Clamp(
            $this->position
        );


        $this->module->SendDebug(
            'TrackingEngine',
            sprintf(
                'Position %.2f %%',
                $this->position
            ),
            0
        );
    }


    public function EstimateRuntime(
        float $target
    ): float {

        $distance = abs(
            $target - $this->position
        );


        if ($target > $this->position) {

            return (
                $distance / 100
            ) * $this->runtimeDown;

        }


        return (
            $distance / 100
        ) * $this->runtimeUp;
    }


    public function IsAtTarget(
        float $target,
        float $tolerance = 0.5
    ): bool {

        return abs(
            $this->position - $target
        ) <= $tolerance;
    }


    public function ReferenceClosed(): void
    {
        /*
         * Referenzpunkt ist geschlossen.
         *
         * Es wird KEINE automatische
         * Referenzfahrt gestartet.
         *
         * Der Wert wird nur nach einer
         * manuellen Referenzfahrt übernommen.
         */

        $this->position = 0.0;


        $this->module->SendDebug(
            'TrackingEngine',
            'Referenz geschlossen gespeichert',
            0
        );
    }


    private function Clamp(
        float $value
    ): float {

        if ($value < 0) {
            return 0.0;
        }


        if ($value > 100) {
            return 100.0;
        }


        return $value;
    }
}