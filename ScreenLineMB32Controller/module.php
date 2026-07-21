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


        $this->RegisterPropertyInteger(
            'RelayUp',
            0
        );

        $this->RegisterPropertyInteger(
            'RelayDown',
            0
        );


        $this->RegisterPropertyFloat(
            'RuntimeUp',
            180
        );

        $this->RegisterPropertyFloat(
            'RuntimeDown',
            180
        );


        $this->RegisterPropertyInteger(
            'SwitchPause',
            400
        );


        $this->RegisterVariableInteger(
            'Position',
            'Position',
            '~Intensity.100',
            10
        );

        $this->EnableAction(
            'Position'
        );


        $this->RegisterVariableString(
            'Status',
            'Status',
            '',
            20
        );


        $this->RegisterAttributeFloat(
            'CurrentPosition',
            0
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
    )
    {

        switch ($Ident) {


            case 'Position':

                $this->MoveTo(
                    (float)$Value
                );

                break;
        }
    }



    private function MoveTo(
        float $target
    )
    {

        $target = max(
            0,
            min(
                100,
                $target
            )
        );


        $current =
            $this->ReadAttributeFloat(
                'CurrentPosition'
            );


        if (abs($current - $target) < 0.5) {

            $this->SetValue(
                'Status',
                'Position bereits erreicht'
            );

            return;
        }



        $relay = new RelayEngine(
            $this,
            $this->ReadPropertyInteger('RelayUp'),
            $this->ReadPropertyInteger('RelayDown'),
            $this->ReadPropertyInteger('SwitchPause')
        );



        if ($target > $current) {

            $direction = 'AB';

            $runtime =
                (($target - $current) / 100)
                *
                $this->ReadPropertyFloat(
                    'RuntimeDown'
                );


            $relay->MoveDown();

        } else {

            $direction = 'AUF';

            $runtime =
                (($current - $target) / 100)
                *
                $this->ReadPropertyFloat(
                    'RuntimeUp'
                );


            $relay->MoveUp();
        }



        $this->SetValue(
            'Status',
            'Fahrt ' . $direction
        );



        IPS_Sleep(
            (int)($runtime * 1000)
        );


        $relay->Stop();



        $this->WriteAttributeFloat(
            'CurrentPosition',
            $target
        );


        $this->SetValue(
            'Position',
            (int)$target
        );


        $this->SetValue(
            'Status',
            'Position erreicht'
        );
    }
}