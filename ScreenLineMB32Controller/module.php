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

    $this->LogMessage(
        'ApplyChanges läuft',
        KL_NOTIFY
    );

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
        $this->SendDebug(
            'RequestAction',
            sprintf(
                'Ident=%s Value=%s',
                (string)$Ident,
                (string)$Value
            ),
            0
        );

        if ($Ident !== 'Position') {
            return;
        }

        $this->SendDebug(
            'RequestAction',
            'MoveTo() wird aufgerufen',
            0
        );

        $this->MoveTo(
            (float)$Value
        );
    }

    private function MoveTo(
        float $target
    ): void {

        $this->SendDebug(
            'MoveTo',
            'Ziel=' . $target,
            0
        );

        $current =
            $this->ReadAttributeFloat(
                'CurrentPosition'
            );

        $this->SendDebug(
            'MoveTo',
            'Aktuelle Position=' . $current,
            0
        );

        if ($target < 0 || $target > 100) {

            $this->SendDebug(
                'MoveTo',
                'Ungültiges Ziel',
                0
            );

            return;
        }

        $relay = new RelayEngine(
            $this,
            $this->ReadPropertyInteger(
                'RelayUp'
            ),
            $this->ReadPropertyInteger(
                'RelayDown'
            ),
            $this->ReadPropertyInteger(
                'SwitchPause'
            )
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

        $this->SendDebug(
            'MoveTo',
            'Runtime=' . $runtime,
            0
        );
        if (
            !$this->movement->Start(
                $current,
                $target,
                $runtime
            )
        ) {

            $this->SendDebug(
                'MoveTo',
                'MovementEngine->Start() liefert FALSE',
                0
            );

            return;
        }

        $this->SendDebug(
            'MoveTo',
            'MovementEngine gestartet',
            0
        );

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

            $this->SendDebug(
                'Timer',
                'MovementEngine ist NULL',
                0
            );

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

        $this->SendDebug(
            'Timer',
            sprintf(
                't=%.1f  Pos=%.1f',
                $elapsed,
                $position
            ),
            0
        );

        if (
            $elapsed >=
            $this->movement->GetRuntime()
        ) {

            $this->SendDebug(
                'Timer',
                'Ziel erreicht',
                0
            );

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