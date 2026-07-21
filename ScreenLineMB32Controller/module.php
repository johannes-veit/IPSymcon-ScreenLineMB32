<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/LCNAdapter.php';
require_once __DIR__ . '/libs/RelayEngine.php';
require_once __DIR__ . '/libs/MovementEngine.php';


class ScreenLineMB32Controller extends IPSModule
{

    private ?MovementEngine $movement = null;


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


        $this->RegisterTimer(
            'MovementTimer',
            500,
            'SLMB32_UpdateMovement($_IPS[\'TARGET\']);'
        );
    }



    public function ApplyChanges()
    {
        parent::ApplyChanges();


        $this->SetTimerInterval(
            'MovementTimer',
            0
        );


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

        if ($Ident === 'Position') {

            $this->MoveTo(
                (float)$Value
            );
        }
    }



    private function MoveTo(
        float $target
    ): void {

        $current =
            $this->ReadAttributeFloat(
                'CurrentPosition'
            );


        if ($target < 0 || $target > 100) {
            return;
        }


        $relay = new RelayEngine(
            $this,
            $this->ReadPropertyInteger('RelayUp'),
            $this->ReadPropertyInteger('RelayDown'),
            $this->ReadPropertyInteger('SwitchPause')
        );


        $this->movement =
            new MovementEngine(
                $this,
                $relay
            );


        if ($target > $current) {

            $runtime =
                (($target - $current) / 100)
                *
                $this->ReadPropertyFloat(
                    'RuntimeDown'
                );

        } else {

            $runtime =
                (($current - $target) / 100)
                *
                $this->ReadPropertyFloat(
                    'RuntimeUp'
                );
        }


        if (!$this->movement->Start(
            $current,
            $target,
            $runtime
        )) {

            return;
        }


        $this->SetTimerInterval(
            'MovementTimer',
            500
        );


        $this->SetValue(
            'Status',
            'Fahrt läuft'
        );
    }



    public function UpdateMovement(): void
    {

        if ($this->movement === null) {
            return;
        }


        static $elapsed = 0;

        $elapsed += 0.5;


        $position =
            $this->movement->CalculatePosition(
                $elapsed
            );


        $this->WriteAttributeFloat(
            'CurrentPosition',
            $position
        );


        $this->SetValue(
            'Position',
            (int)$position
        );


        if (
            $elapsed >=
            $this->movement->GetRuntime()
        ) {

            $this->movement->Stop();


            $this->WriteAttributeFloat(
                'CurrentPosition',
                $this->movement->GetTarget()
            );


            $this->SetTimerInterval(
                'MovementTimer',
                0
            );


            $this->SetValue(
                'Position',
                (int)$this->movement->GetTarget()
            );


            $this->SetValue(
                'Status',
                'Position erreicht'
            );


            $elapsed = 0;
        }
    }
}