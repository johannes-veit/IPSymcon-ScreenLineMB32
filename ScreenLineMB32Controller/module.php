<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/LCNAdapter.php';
require_once __DIR__ . '/libs/RelayEngine.php';
require_once __DIR__ . '/libs/MovementEngine.php';
require_once __DIR__ . '/libs/SafetyEngine.php';
require_once __DIR__ . '/libs/ShakeFree.php';
require_once __DIR__ . '/libs/TrackingEngine.php';

class ScreenLineMB32Controller extends IPSModule
{
    public function Create(): void
    {
        parent::Create();

        // IPS 9 Typisierte Properties
        $this->RegisterPropertyInteger('RelayUp', 0);
        $this->RegisterPropertyInteger('RelayDown', 0);
        $this->RegisterPropertyFloat('RuntimeUp', 180.0);
        $this->RegisterPropertyFloat('RuntimeDown', 180.0);
        $this->RegisterPropertyInteger('SwitchPause', 400);
        $this->RegisterPropertyBoolean('ShakeFreeEnabled', false);
        $this->RegisterPropertyFloat('ShakeFreeDuration', 2.0);

        // Statusvariablen (Kompatibel für IPS 9 TileVisu)
        $this->RegisterVariableInteger('Position', 'Position', '~Intensity.100', 10);
        $this->EnableAction('Position');
        $this->RegisterVariableString('Status', 'Status', '', 20);

        // Persistente Attribute für die Rehydrierung
        $this->RegisterAttributeFloat('CurrentPosition', 0.0);
        $this->RegisterAttributeFloat('TargetPosition', 0.0);
        $this->RegisterAttributeFloat('LastTimestamp', 0.0);
        $this->RegisterAttributeInteger('CurrentDirection', 0); 
        $this->RegisterAttributeInteger('ShakeFreeStep', 0);    
        $this->RegisterAttributeFloat('SafetyStartTime', 0.0);
        $this->RegisterAttributeBoolean('SafetyRunning', false);

        // Timer registrieren
        $this->RegisterTimer('MovementTimer', 0, 'SLMB32_UpdateMovement($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->LogMessage('ApplyChanges läuft', KL_NOTIFY);
        $this->SetTimerInterval('MovementTimer', 0);
        $this->SetValue('Status', 'Bereit');
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->SendDebug('RequestAction', sprintf('Ident=%s Value=%s', (string)$Ident, (string)$Value), 0);

        if ($Ident !== 'Position') {
            return;
        }

        $this->MoveTo((float)$Value);
    }

    public function MoveTo(float $target): void
    {
        $this->SendDebug('MoveTo', 'Ziel=' . $target, 0);
        $current = $this->ReadAttributeFloat('CurrentPosition');

        if ($target < 0.0 || $target > 100.0) {
            $this->SendDebug('MoveTo', 'Ungültiges Ziel', 0);
            return;
        }

        $shake = new ShakeFree($this, $this->ReadPropertyBoolean('ShakeFreeEnabled'), $this->ReadPropertyFloat('ShakeFreeDuration'));
        if ($shake->IsEnabled() && $shake->Validate() && $target === 100.0 && $this->ReadAttributeInteger('ShakeFreeStep') === 0) {
            $this->WriteAttributeInteger('ShakeFreeStep', 1);
            $this->SetValue('Status', 'Shake-Free gestartet');
            $realTarget = $shake->GetNextSequenceTarget(1, $target);
        } else {
            $realTarget = $target;
        }

        if ($current === $realTarget) {
            if ($this->ReadAttributeInteger('ShakeFreeStep') > 0) {
                $this->AdvanceShakeFreeSequence($target);
                return;
            }
            $this->SetValue('Status', 'Position erreicht');
            return;
        }

        $relay = new RelayEngine($this, $this->ReadPropertyInteger('RelayUp'), $this->ReadPropertyInteger('RelayDown'), $this->ReadPropertyInteger('SwitchPause'));
        $movement = new MovementEngine($this, $relay);

        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'));
        $tracking->Rehydrate($current, microtime(true));
        $runtime = $tracking->EstimateRuntime($realTarget);

        $safety = new SafetyEngine($this, $runtime + 10.0);
        if (!$safety->ValidateRelayCombination($this->ReadPropertyInteger('RelayUp'), $this->ReadPropertyInteger('RelayDown')) || !$safety->ValidateRuntime($runtime)) {
            $this->SetValue('Status', 'Fehler: Validierung fehlgeschlagen');
            return;
        }

        if (!$movement->Start($current, $realTarget, $runtime)) {
            $this->SendDebug('MoveTo', 'MovementEngine->Start() liefert FALSE', 0);
            return;
        }

        $direction = ($realTarget > $current) ? TrackingEngine::DIRECTION_DOWN : TrackingEngine::DIRECTION_UP;
        $this->WriteAttributeInteger('CurrentDirection', $direction);
        $this->WriteAttributeFloat('TargetPosition', $realTarget);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));

        $safetyStartTime = $safety->Start();
        $this->WriteAttributeFloat('SafetyStartTime', $safetyStartTime);
        $this->WriteAttributeBoolean('SafetyRunning', true);

        $this->SetTimerInterval('MovementTimer', 500);
        $this->SetValue('Status', 'Fahrt läuft');
    }

    public function UpdateMovement(): void
    {
        $direction = $this->ReadAttributeInteger('CurrentDirection');
        $target = $this->ReadAttributeFloat('TargetPosition');
        $current = $this->ReadAttributeFloat('CurrentPosition');
        $lastTimestamp = $this->ReadAttributeFloat('LastTimestamp');

        $relay = new RelayEngine($this, $this->ReadPropertyInteger('RelayUp'), $this->ReadPropertyInteger('RelayDown'), $this->ReadPropertyInteger('SwitchPause'));

        $trackingDummy = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'));
        $trackingDummy->Rehydrate($current, $lastTimestamp);
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

        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'));
        $tracking->Rehydrate($current, $lastTimestamp);
        $tracking->Move($direction);

        $newPos = $tracking->GetPosition();
        $this->WriteAttributeFloat('CurrentPosition', $newPos);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));
        $this->SetValue('Position', (int)$newPos);

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
        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'));
        $tracking->ReferenceClosed();
        $this->WriteAttributeFloat('CurrentPosition', 0.0);
        $this->SetValue('Position', 0);
        $this->SetValue('Status', 'Referenz geschlossen gespeichert');
    }
}
