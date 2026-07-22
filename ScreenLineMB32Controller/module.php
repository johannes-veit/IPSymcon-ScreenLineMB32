{
    "elements": [
        {
            "type": "ExpansionPanel",
            "caption": "Hardware-Konfiguration (LCN)",
            "expanded": true,
            "items": [
                {
                    "type": "SelectInstance",
                    "name": "RelayUp",
                    "caption": "LCN-Relais für AUF"
                },
                {
                    "type": "SelectInstance",
                    "name": "RelayDown",
                    "caption": "LCN-Relais für AB"
                },
                {
                    "type": "NumberSpinner",
                    "name": "SwitchPause",
                    "caption": "Umschaltpause (ms)",
                    "minimum": 50,
                    "maximum": 2000,
                    "suffix": " ms"
                }
            ]
        },
        {
            "type": "ExpansionPanel",
            "caption": "Fahrzeiten & Trägheitskompensation",
            "expanded": true,
            "items": [
                {
                    "type": "NumberSpinner",
                    "name": "RuntimeUp",
                    "caption": "Reine Laufzeit nach OBEN (ohne Trägheit)",
                    "minimum": 1.0,
                    "maximum": 600.0,
                    "digits": 1,
                    "suffix": " Sek."
                },
                {
                    "type": "NumberSpinner",
                    "name": "RuntimeDown",
                    "caption": "Reine Laufzeit nach UNTEN (ohne Trägheit)",
                    "minimum": 1.0,
                    "maximum": 600.0,
                    "digits": 1,
                    "suffix": " Sek."
                },
                {
                    "type": "NumberSpinner",
                    "name": "SlatTurnTime",
                    "caption": "Lamellenwendezeit bei Richtungswechsel",
                    "minimum": 0.0,
                    "maximum": 10.0,
                    "digits": 1,
                    "suffix": " Sek."
                },
                {
                    "type": "NumberSpinner",
                    "name": "SoftStartTime",
                    "caption": "Sanftanlaufzeit (bei jedem Fahrtantritt)",
                    "minimum": 0.0,
                    "maximum": 5.0,
                    "digits": 1,
                    "suffix": " Sek."
                }
            ]
        },
        {
            "type": "ExpansionPanel",
            "caption": "ScreenLine MB32 Spezialfunktionen",
            "expanded": false,
            "items": [
                {
                    "type": "CheckBox",
                    "name": "ShakeFreeEnabled",
                    "caption": "Shake-Free (Lamellenentspannung) aktivieren"
                },
                {
                    "type": "NumberSpinner",
                    "name": "ShakeFreeDuration",
                    "caption": "Shake-Free Impulsdauer",
                    "minimum": 0.1,
                    "maximum": 10.0,
                    "digits": 1,
                    "suffix": " Sek."
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Test: Fahre auf 0% (Ganz OBEN)",
            "onClick": "SLMB32_MoveTo($id, 0);"
        },
        {
            "type": "Button",
            "caption": "Test: Fahre auf 50% (Mitte)",
            "onClick": "SLMB32_MoveTo($id, 50);"
        },
        {
            "type": "Button",
            "caption": "Test: Fahre auf 100% (Ganz UNTEN)",
            "onClick": "SLMB32_MoveTo($id, 100);"
        },
        {
            "type": "Button",
            "caption": "Referenzieren (Geschlossen)",
            "onClick": "SLMB32_ReferenceClosed($id);"
        }
    ]
}
    public function UpdateMovement(): void
    {
        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');

        if ($relayUp <= 0 || $relayDown <= 0 || !IPS_InstanceExists($relayUp) || !IPS_InstanceExists($relayDown)) {
            $this->SetTimerInterval('MovementTimer', 0);
            return;
        }

        $direction = $this->ReadAttributeInteger('CurrentDirection');
        $target = $this->ReadAttributeFloat('TargetPosition');
        $current = $this->ReadAttributeFloat('CurrentPosition');
        $lastTimestamp = $this->ReadAttributeFloat('LastTimestamp');
        $remSlat = $this->ReadAttributeFloat('RemainingSlatTime');
        $remSoft = $this->ReadAttributeFloat('RemainingSoftStartTime');

        $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));

        $trackingDummy = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'), $remSlat, $remSoft);
        $trackingDummy->Rehydrate($current, $lastTimestamp, $remSlat, $remSoft);
        $estimated = $trackingDummy->EstimateRuntime($target);

        $safety = new SafetyEngine($this, $estimated + 10.0);
        $safety->Rehydrate($this->ReadAttributeFloat('SafetyStartTime'), $this->ReadAttributeBoolean('SafetyRunning'));

        if ($safety->IsTimeout()) {
            $safety->SafeStop($relay);
            $this->SetTimerInterval('MovementTimer', 0);
            $this->WriteAttributeBoolean('SafetyRunning', false);
            $this->WriteAttributeInteger('CurrentDirection', 0);
            $this->WriteAttributeInteger('ShakeFreeStep', 0);
            $this->SetValue('Status', 'NOT-AUS: Timeout');
            return;
        }

        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'), $remSlat, $remSoft);
        $tracking->Rehydrate($current, $lastTimestamp, $remSlat, $remSoft);
        $tracking->Move($direction);

        $newPos = $tracking->GetPosition();
        $this->WriteAttributeFloat('CurrentPosition', $newPos);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));
        $this->WriteAttributeFloat('RemainingSlatTime', $tracking->GetRemainingSlatTime());
        $this->WriteAttributeFloat('RemainingSoftStartTime', $tracking->GetRemainingSoftStartTime());
        $this->SetValue('Position', (int)$newPos);

        // Optische Statusrückmeldung anpassen
        if ($tracking->GetRemainingSoftStartTime() > 0.0) {
            $this->SetValue('Status', 'Sanftanlauf active...');
        } elseif ($tracking->GetRemainingSlatTime() > 0.0) {
            $this->SetValue('Status', 'Lamellenwendung active...');
        } else {
            $this->SetValue('Status', 'Fahrt läuft');
        }

        if ($tracking->IsAtTarget($target) || ($direction === TrackingEngine::DIRECTION_UP && $newPos <= 0.0) || ($direction === TrackingEngine::DIRECTION_DOWN && $newPos >= 100.0)) {
            $relay->Stop();
            $safety->Stop();
            $this->WriteAttributeBoolean('SafetyRunning', false);
            $this->WriteAttributeFloat('CurrentPosition', $target);
            $this->SetValue('Position', (int)$target);

            $coldStep = $this->ReadAttributeInteger('ShakeFreeStep');
            if ($coldStep > 0) {
                $this->AdvanceShakeFreeSequence(100.0); 
            } else {
                $this->SetTimerInterval('MovementTimer', 0);
                $this->WriteAttributeInteger('CurrentDirection', 0);
                $this->SetValue('Status', 'Position erreicht');
            }
        }
    }

    private function AdvanceShakeFreeSequence(float $finalTarget): void
    {
        $currentStep = $this->ReadAttributeInteger('ShakeFreeStep');
        $nextStep = $currentStep + 1;

        $shake = new ShakeFree($this, $this->ReadPropertyBoolean('ShakeFreeEnabled'), $this->ReadPropertyFloat('ShakeFreeDuration'));
        $nextTarget = $shake->GetNextSequenceTarget($nextStep, $finalTarget);

        if ($nextTarget !== null) {
            $this->WriteAttributeInteger('ShakeFreeStep', $nextStep);
            $this->SetValue('Status', 'Shake-Free Phase ' . $nextStep);
            $this->SetTimerInterval('MovementTimer', 0); 
            $this->MoveTo($nextTarget);
        } else {
            $this->WriteAttributeInteger('ShakeFreeStep', 0);
            $this->WriteAttributeInteger('CurrentDirection', 0);
            $this->SetTimerInterval('MovementTimer', 0);
            $this->SetValue('Status', 'Position erreicht');
        }
    }

    public function ReferenceClosed(): void
    {
        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'), 0.0, 0.0);
        $tracking->ReferenceClosed();
        $this->WriteAttributeFloat('CurrentPosition', 100.0);
        $this->SetValue('Position', 100);
        $this->SetValue('Status', 'Referenz geschlossen gespeichert (100%)');
    }

    public function GetModuleInstanceID(): int
    {
        return $this->InstanceID;
    }
}
