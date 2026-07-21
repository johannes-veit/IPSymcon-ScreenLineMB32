<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/RelayEngine.php';
require_once __DIR__ . '/libs/SafetyEngine.php';
require_once __DIR__ . '/libs/TrackingEngine.php';
require_once __DIR__ . '/libs/ShakeFree.php';


class ScreenLineMB32Controller extends IPSModule
{
    public function Create()
    {
        parent::Create();


        /*
         * Hardware
         */

        $this->RegisterPropertyInteger(
            'RelayUp',
            0
        );

        $this->RegisterPropertyInteger(
            'RelayDown',
            0
        );


        /*
         * Motorlaufzeiten
         */

        $this->RegisterPropertyFloat(
            'RuntimeUp',
            180.0
        );

        $this->RegisterPropertyFloat(
            'RuntimeDown',
            180.0
        );


        /*
         * Sicherheit
         */

        $this->RegisterPropertyInteger(
            'SwitchPause',
            400
        );

        $this->RegisterPropertyFloat(
            'MotorTimeout',
            190.0
        );


        /*
         * Shake Free
         */

        $this->RegisterPropertyBoolean(
            'ShakeFreeEnabled',
            true
        );

        $this->RegisterPropertyFloat(
            'ShakeFreeDuration',
            1.5
        );


        /*
         * interne Werte
         */

        $this->RegisterAttributeFloat(
            'Position',
            0.0
        );

        $this->RegisterAttributeFloat(
            'Lamellen',
            0.0
        );


        /*
         * Visualisierung
         */

        $this->RegisterVariableInteger(
            'BlindControl',
            'Position',
            '~Intensity.100',
            10
        );

        $this->EnableAction(
            'BlindControl'
        );


        $this->RegisterVariableInteger(
            'SlatPosition',
            'Lamellen',
            '~Intensity.100',
            20
        );

        $this->EnableAction(
            'SlatPosition'
        );


        $this->RegisterVariableString(
            'Status',
            'Status',
            '',
            30
        );
    }


    public function ApplyChanges()
    {
        parent::ApplyChanges();


        $this->SetValue(
            'Status',
            'Bereit'
        );
    }


    public function RequestAction(
        $Ident,
        $Value
    ) {

        switch ($Ident) {


            case 'BlindControl':

                $this->MoveToPosition(
                    (float)$Value
                );

                break;


            case 'SlatPosition':

                $this->MoveSlats(
                    (float)$Value
                );

                break;
        }
    }


    private function MoveToPosition(
        float $position
    ): void {

        $this->SendDebug(
            'ScreenLine',
            'Zielposition: ' . $position,
            0
        );


        $this->SetValue(
            'Status',
            'Fahrt vorbereitet'
        );
    }


    private function MoveSlats(
        float $position
    ): void {

        $this->SendDebug(
            'ScreenLine',
            'Lamellenziel: ' . $position,
            0
        );


        $this->SetValue(
            'Status',
            'Lamellenfahrt vorbereitet'
        );
    }
}