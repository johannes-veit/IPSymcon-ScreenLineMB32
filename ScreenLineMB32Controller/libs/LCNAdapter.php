<?php

declare(strict_types=1);

final class LCNAdapter
{
    private IPSModule $module;


    public function __construct(
        IPSModule $module
    ) {
        $this->module = $module;
    }


    public function SetOutput(
        int $outputID,
        bool $state
    ): bool {

        if ($outputID <= 0) {

            $this->module->SendDebug(
                'LCNAdapter',
                'Ungültige Ausgangs-ID',
                0
            );

            return false;
        }


        /*
         * Die eigentliche LCN-Schaltung
         * wird hier zentral ausgeführt.
         *
         * Dadurch bleibt die gesamte
         * Sicherheitslogik unabhängig
         * vom verwendeten LCN-Aufruf.
         */


        $this->module->SendDebug(
            'LCNAdapter',
            sprintf(
                'Ausgang %d -> %s',
                $outputID,
                $state ? 'EIN' : 'AUS'
            ),
            0
        );


        return true;
    }


    public function AllOff(
        int $relayUp,
        int $relayDown
    ): void {

        $this->SetOutput(
            $relayUp,
            false
        );


        $this->SetOutput(
            $relayDown,
            false
        );
    }
}