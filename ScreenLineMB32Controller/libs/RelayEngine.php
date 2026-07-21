<?php

declare(strict_types=1);

final class RelayEngine
{
    private IPSModule $module;

    private int $relayUp;

    private int $relayDown;

    private int $switchPause;


    public function __construct(
        IPSModule $module,
        int $relayUp,
        int $relayDown,
        int $switchPause = 400
    ) {
        $this->module = $module;
        $this->relayUp = $relayUp;
        $this->relayDown = $relayDown;
        $this->switchPause = $switchPause;
    }


    public function MoveUp(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->AllOff();

        usleep($this->switchPause * 1000);

        return $this->SetRelay(
            $this->relayUp,
            true
        );
    }


    public function MoveDown(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->AllOff();

        usleep($this->switchPause * 1000);

        return $this->SetRelay(
            $this->relayDown,
            true
        );
    }


    public function Stop(): void
    {
        $this->AllOff();
    }


    public function AllOff(): void
    {
        if ($this->relayUp > 0) {
            $this->SetRelay(
                $this->relayUp,
                false
            );
        }

        if ($this->relayDown > 0) {
            $this->SetRelay(
                $this->relayDown,
                false
            );
        }
    }


    private function Validate(): bool
    {
        if ($this->relayUp <= 0) {
            $this->module->SendDebug(
                'RelayEngine',
                'AUF Relais nicht konfiguriert',
                0
            );

            return false;
        }


        if ($this->relayDown <= 0) {
            $this->module->SendDebug(
                'RelayEngine',
                'AB Relais nicht konfiguriert',
                0
            );

            return false;
        }


        if ($this->relayUp === $this->relayDown) {

            $this->module->SendDebug(
                'RelayEngine',
                'Fehler: AUF und AB Relais identisch',
                0
            );

            return false;
        }


        return true;
    }


    private function SetRelay(
        int $relay,
        bool $state
    ): bool {

        $this->module->SendDebug(
            'RelayEngine',
            sprintf(
                'Relais %d -> %s',
                $relay,
                $state ? 'EIN' : 'AUS'
            ),
            0
        );


        /*
         * Hier wird später die LCN-Schaltfunktion
         * eingebunden.
         *
         * Die Trennung ist bewusst:
         * RelayEngine entscheidet Sicherheit,
         * LCN-Anbindung kommt separat.
         */

        return true;
    }
}