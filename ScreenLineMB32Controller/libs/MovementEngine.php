<?php

declare(strict_types=1);

final class MovementEngine
{
    private IPSModule $module;

    private RelayEngine $relay;

    private float $startPosition;

    private float $targetPosition;

    private float $runtime;

    private string $direction;


    public function __construct(
        IPSModule $module,
        RelayEngine $relay
    ) {
        $this->module = $module;
        $this->relay = $relay;
    }


    public function Start(
        float $current,
        float $target,
        float $runtime
    ): bool {

        if ($current === $target) {
            return false;
        }


        $this->startPosition = $current;
        $this->targetPosition = $target;
        $this->runtime = $runtime;


        if ($target > $current) {

            $this->direction = 'DOWN';

            if (!$this->relay->MoveDown()) {
                return false;
            }

        } else {

            $this->direction = 'UP';

            if (!$this->relay->MoveUp()) {
                return false;
            }
        }


        $this->module->SendDebug(
            'MovementEngine',
            'Fahrt gestartet Richtung ' . $this->direction,
            0
        );


        return true;
    }



    public function Stop(): void
    {
        $this->relay->Stop();


        $this->module->SendDebug(
            'MovementEngine',
            'Fahrt gestoppt',
            0
        );
    }



    public function GetDirection(): string
    {
        return $this->direction;
    }



    public function GetRuntime(): float
    {
        return $this->runtime;
    }



    public function GetTarget(): float
    {
        return $this->targetPosition;
    }



    public function CalculatePosition(
        float $elapsed
    ): float {

        if ($this->runtime <= 0) {
            return $this->startPosition;
        }


        $change =
            ($elapsed / $this->runtime)
            * 100;


        if ($this->direction === 'UP') {

            return max(
                0,
                $this->startPosition - $change
            );
        }


        return min(
            100,
            $this->startPosition + $change
        );
    }
}