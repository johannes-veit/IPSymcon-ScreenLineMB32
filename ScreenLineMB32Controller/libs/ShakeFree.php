<?php

declare(strict_types=1);

final class ShakeFree
{
    private IPSModule $module;

    private float $duration;

    private bool $enabled;


    public function __construct(
        IPSModule $module,
        bool $enabled,
        float $duration
    ) {
        $this->module = $module;
        $this->enabled = $enabled;
        $this->duration = $duration;
    }


    public function IsEnabled(): bool
    {
        return $this->enabled;
    }


    public function Execute(): array
    {
        if (!$this->enabled) {

            return [];
        }


        /*
         * ScreenLine MB32:
         *
         * Die Lamellen können nach dem Abwickeln
         * mechanisch leicht verspannt hängen.
         *
         * Die Shake-Free Funktion bewegt deshalb
         * die Jalousie einmal vollständig:
         *
         * geschlossen -> komplett geöffnet
         * -> zurück zur Zielposition
         *
         * Es wird bewusst nur die starke Variante
         * verwendet.
         */


        $this->module->SendDebug(
            'ShakeFree',
            'Shake-Free Sequenz vorbereitet',
            0
        );


        return [
            'StartPosition' => 0.0,
            'EndPosition'   => 100.0,
            'Return'        => true,
            'Duration'      => $this->duration
        ];
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


        if ($this->duration <= 0) {
            return false;
        }


        if ($this->duration > 10) {
            return false;
        }


        return true;
    }
}