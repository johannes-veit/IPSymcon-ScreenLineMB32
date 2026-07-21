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


        // Relais

        $this->RegisterPropertyInteger(
            'RelayUp',
            0
        );

        $this->RegisterPropertyInteger(
            'RelayDown',
            0
        );


        // Laufzeiten

        $this->RegisterPropertyFloat(
            'RuntimeUp',
            180.0
        );

        $this->RegisterPropertyFloat(
            'RuntimeDown',
            180.0
        );


        // Sicherheit

        $this->RegisterPropertyInteger(
            'SwitchPause',
            400
        );

        $this->RegisterPropertyFloat(
            'MotorTimeout',
            190.0
        );


        // Shake Free

        $this->RegisterPropertyBoolean(
            'ShakeFreeEnabled',
            true
        );

        $this->RegisterPropertyFloat(
            'ShakeFreeDuration',
            1.5
        );


        $this->RegisterPropertyBoolean(
            'EnableDiagnostics',
            false
        );


        // interne Attribute

        $this->RegisterAttributeFloat(
            'PositionFloat',
            0.0
        );

        $this->RegisterAttributeFloat(
            'SlatFloat',
            0.0
        );

        $this->RegisterAttributeInteger(
            'Direction',
            0
        );

        $this->RegisterAttributeBoolean(
            'Referenced',
            false
        );


        // Visualisierung

        $this->RegisterVariableInteger(
            'Position',
            'Position',
            '~Intensity.100',
            10
        );

        $this->EnableAction(
            'Position'
        );


        $this->RegisterVariableInteger(
            'Lamellen',
            'Lamellen',
            '~Intensity.100',
            20
        );

        $this->EnableAction(
            'Lamellen'
        );


        $this->RegisterVariableString(
            'Status',
            'Status',
            '',
            30
        );


        // Timer

        $this->RegisterTimer(
            'Tracking',
            1000,
            'SLMB32_Tracking($_IPS[\'TARGET\']);'
        );

        $this->RegisterTimer(
            'Watchdog',
            1000,
            'SLMB32_Watchdog($_IPS[\'TARGET\']);'
        );
    }


    public function ApplyChanges()
    {
        parent::ApplyChanges();


        $this->SetValue(
            'Status',
            'Bereit'
        );


        $this->ValidateConfiguration();
    }


    private function ValidateConfiguration()
    {
        if ($this->ReadPropertyInteger('RelayUp') ===
            $this->ReadPropertyInteger('RelayDown')) {

            $this->SetValue(
                'Status',
                'Fehler: Relais identisch'
            );

            return;
        }
    }


    public function RequestAction(
        $Ident,
        $Value
    ) {

        switch ($Ident) {


            case 'Position':

                $this->MovePosition(
                    (float)$Value
                );

                break;


            case 'Lamellen':

                $this->MoveLamellen(
                    (float)$Value
                );

                break;
        }
    }


    private function MovePosition(
        float $Value
    )
    {
        $this->SendDebug(
            'Position',
            (string)$Value,
            0
        );

        $this->SetValue(
            'Status',
            'Fahrt vorbereitet'
        );
    }


    private function MoveLamellen(
        float $Value
    )
    {
        $this->SendDebug(
            'Lamellen',
            (string)$Value,
            0
        );

        $this->SetValue(
            'Status',
            'Lamellen vorbereitet'
        );
    }


    public function Tracking()
    {

    }


    public function Watchdog()
    {

    }
}