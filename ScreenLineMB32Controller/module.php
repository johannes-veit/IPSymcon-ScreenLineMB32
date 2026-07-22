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

        // IP-Symcon 9.0 Typisierte Properties
        $this->RegisterPropertyInteger('RelayUp', 0);
        $this->RegisterPropertyInteger('RelayDown', 0);
        $this->RegisterPropertyFloat('RuntimeUp', 180.0);
        $this->RegisterPropertyFloat('RuntimeDown', 180.0);
        $this->RegisterPropertyInteger('SwitchPause', 400);
        $this->RegisterPropertyBoolean('ShakeFreeEnabled', false);
        $this->RegisterPropertyFloat('ShakeFreeDuration', 2.0);
        $this->RegisterPropertyFloat('SlatTurnTime', 1.5);
        $this->RegisterPropertyFloat('SoftStartTime', 0.5);
        
        // Zwischenstopp-Shake-Free Properties
        $this->RegisterPropertyBoolean('IntermediateShakeEnabled', false);
        $this->RegisterPropertyFloat('IntermediateShakeDuration', 6.0);

        // Statusvariablen registrieren
        $this->RegisterVariableInteger('Position', 'Position', '~Intensity.100', 10);
        $this->EnableAction('Position');

        // Profil absichern ohne Tilde
        if (!IPS_VariableProfileExists('SlatPosition')) {
            IPS_CreateVariableProfile('SlatPosition', 1);
            IPS_SetVariableProfileValues('SlatPosition', 0, 100, 1);
            IPS_SetVariableProfileText('SlatPosition', '%', ' %');
        }

        $this->RegisterVariableInteger('SlatPosition', 'Lamelle', 'SlatPosition', 15);
        $this->EnableAction('SlatPosition');
        
        $this->RegisterVariableString('Status', 'Status', '', 20);

        // Persistente Attribute für die Timer-Rehydrierung
        $this->RegisterAttributeFloat('CurrentPosition', 0.0);
        $this->RegisterAttributeFloat('TargetPosition', 0.0);
        $this->RegisterAttributeFloat('CurrentSlatPosition', 0.0);
        $this->RegisterAttributeFloat('TargetSlatPosition', 0.0);
        $this->RegisterAttributeFloat('LastTimestamp', 0.0);
        $this->RegisterAttributeInteger('CurrentDirection', 0); 
        $this->RegisterAttributeInteger('ShakeFreeStep', 0);    
        $this->RegisterAttributeFloat('SafetyStartTime', 0.0);
        $this->RegisterAttributeBoolean('SafetyRunning', false);
        $this->RegisterAttributeFloat('RemainingSlatTime', 0.0);
        $this->RegisterAttributeFloat('RemainingSoftStartTime', 0.0);
        $this->RegisterAttributeFloat('OverrunRemainingTime', 0.0);
        $this->RegisterAttributeBoolean('IsSlatOnlyMove', false);
        
        // Attribute zur Stillstands-Überwachung für den Standby-Modus
        $this->RegisterAttributeFloat('LastLoggedPosition', -1.0);
        
        // FIX: Korrekte Registrierung des Attributes (StaticPositionDuration) vor der Benutzung
        $this->RegisterAttributeFloat('StaticPositionDuration', 0.0);

        // Zustands-Verwaltung für die Zwischenstopp-Sequenz
        $this->RegisterAttributeInteger('IntermediateShakeStep', 0);
        $this->RegisterAttributeFloat('IntermediateShakeRemaining', 0.0);
        $this->RegisterAttributeInteger('OriginalDirection', 0);

        // Timer-Aufruf registrieren
        $this->RegisterTimer('MovementTimer', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "UpdateMovement", 0);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->UnregisterAllMessages();

        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');

        // Bidirektionales LCN-Relais Tracking aktivieren
        if ($relayUp > 0 && IPS_InstanceExists($relayUp)) {
            $upStatusID = @IPS_GetObjectIDByIdent('Status', $relayUp);
            if ($upStatusID > 0) {
                $this->RegisterMessage($upStatusID, VM_UPDATE);
            }
        }
        if ($relayDown > 0 && IPS_InstanceExists($relayDown)) {
            $downStatusID = @IPS_GetObjectIDByIdent('Status', $relayDown);
            if ($downStatusID > 0) {
                $this->RegisterMessage($downStatusID, VM_UPDATE);
            }
        }

        $this->SetTimerInterval('MovementTimer', 0);
        $this->SetValue('Status', 'Bereit');
    }
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');
        
        $upStatusID = @IPS_GetObjectIDByIdent('Status', $relayUp);
        $downStatusID = @IPS_GetObjectIDByIdent('Status', $relayDown);

        $isRelayUpActive = ($upStatusID > 0) ? GetValueBoolean($upStatusID) : false;
        $isRelayDownActive = ($downStatusID > 0) ? GetValueBoolean($downStatusID) : false;
        $timerInterval = $this->GetTimerInterval('MovementTimer');

        // Externe Hardwarefahrt über LCN erkannt -> Tracking aufwecken
        if (($isRelayUpActive || $isRelayDownActive) && $timerInterval === 0) {
            $direction = $isRelayDownActive ? TrackingEngine::DIRECTION_DOWN : TrackingEngine::DIRECTION_UP;
            $current = $this->ReadAttributeFloat('CurrentPosition');
            $currentSlat = $this->ReadAttributeFloat('CurrentSlatPosition');
            $target = ($direction === TrackingEngine::DIRECTION_DOWN) ? 100.0 : 0.0;

            $slatTime = ($direction === TrackingEngine::DIRECTION_DOWN) 
                ? (((100.0 - $currentSlat) / 100.0) * $this->ReadPropertyFloat('SlatTurnTime'))
                : (($currentSlat / 100.0) * $this->ReadPropertyFloat('SlatTurnTime'));
            
            $softTime = $this->ReadPropertyFloat('SoftStartTime');

            $this->WriteAttributeInteger('CurrentDirection', $direction);
            $this->WriteAttributeFloat('TargetPosition', $target);
            $this->WriteAttributeFloat('LastTimestamp', microtime(true));
            $this->WriteAttributeFloat('RemainingSlatTime', $slatTime);
            $this->WriteAttributeFloat('RemainingSoftStartTime', $softTime);
            $this->WriteAttributeFloat('OverrunRemainingTime', 0.0);
            $this->WriteAttributeBoolean('IsSlatOnlyMove', false);
            $this->WriteAttributeFloat('LastLoggedPosition', $current);
            $this->WriteAttributeFloat('StaticPositionDuration', 0.0);
            $this->WriteAttributeInteger('IntermediateShakeStep', 0);

            $this->SetTimerInterval('MovementTimer', 500);
            $this->SetValue('Status', 'LCN-Fahrt läuft...');
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === 'Position') {
            $this->MoveTo((float)$Value);
        } elseif ($Ident === 'SlatPosition') {
            $this->MoveSlatTo((float)$Value);
        } elseif ($Ident === 'UpdateMovement') {
            $this->UpdateMovement();
        }
    }

    public function MoveTo(float $target): void
    {
        $this->SendDebug('MoveTo', 'Ziel=' . $target, 0);
        $current = $this->ReadAttributeFloat('CurrentPosition');
        $currentSlat = $this->ReadAttributeFloat('CurrentSlatPosition');

        if ($target < 0.0 || $target > 100.0) return;

        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');
        if ($relayUp <= 0 || $relayDown <= 0 || !IPS_InstanceExists($relayUp) || !IPS_InstanceExists($relayDown)) {
            $this->SetValue('Status', 'Fehler: Relais fehlen');
            return;
        }

        if ($this->GetTimerInterval('MovementTimer') > 0 && abs($current - $target) < 0.1) {
            $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
            if ($this->ReadPropertyBoolean('IntermediateShakeEnabled') && $this->ReadAttributeInteger('IntermediateShakeStep') === 0) {
                $this->TriggerIntermediateShake();
                return;
            }
            $relay->Stop(); 
            $this->SetTimerInterval('MovementTimer', 0); 
            $this->WriteAttributeInteger('CurrentDirection', 0);
            $this->WriteAttributeInteger('IntermediateShakeStep', 0);
            $this->SetValue('Status', 'Standby: Manueller Stopp');
            return;
        }

        $shake = new ShakeFree($this, $this->ReadPropertyBoolean('ShakeFreeEnabled'), $this->ReadPropertyFloat('ShakeFreeDuration'));
        $realTarget = $target;
        if ($shake->IsEnabled() && $target === 100.0 && $this->ReadAttributeInteger('ShakeFreeStep') === 0) {
            $this->WriteAttributeInteger('ShakeFreeStep', 1);
            $sequenceTarget = $shake->GetNextSequenceTarget(1, $target);
            if ($sequenceTarget !== null) $realTarget = $sequenceTarget;
        }

        if ($current === $realTarget && $realTarget !== 100.0 && $realTarget !== 0.0) {
            if ($this->ReadPropertyBoolean('IntermediateShakeEnabled') && $this->ReadAttributeInteger('IntermediateShakeStep') === 0) {
                $this->TriggerIntermediateShake();
                return;
            }
            $this->SetValue('Status', 'Position erreicht');
            return;
        }

        $newDirection = ($realTarget > $current || ($current === 100.0 && $realTarget === 100.0)) ? TrackingEngine::DIRECTION_DOWN : TrackingEngine::DIRECTION_UP;
        $slatTime = ($newDirection === TrackingEngine::DIRECTION_DOWN)
            ? (((100.0 - $currentSlat) / 100.0) * $this->ReadPropertyFloat('SlatTurnTime'))
            : (($currentSlat / 100.0) * $this->ReadPropertyFloat('SlatTurnTime'));
        
        $softTime = $this->ReadPropertyFloat('SoftStartTime');
        $overrunTime = ($realTarget === 100.0 || $realTarget === 0.0) ? 5.0 : 0.0;

        $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
        $movement = new MovementEngine($this, $relay);
        $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'), $slatTime, $softTime);
        $tracking->Rehydrate($current, $currentSlat, microtime(true), $slatTime, $softTime);
        $runtime = $tracking->EstimateRuntime($realTarget) + $overrunTime;

        $safety = new SafetyEngine($this, $runtime + 10.0);
        if (!$movement->Start($current, $realTarget, $runtime)) return;

        $this->WriteAttributeInteger('CurrentDirection', $newDirection);
        $this->WriteAttributeFloat('TargetPosition', $realTarget);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));
        $this->WriteAttributeFloat('RemainingSlatTime', $slatTime);
        $this->WriteAttributeFloat('RemainingSoftStartTime', $softTime);
        $this->WriteAttributeFloat('OverrunRemainingTime', $overrunTime);
        $this->WriteAttributeBoolean('IsSlatOnlyMove', false);
        $this->WriteAttributeFloat('LastLoggedPosition', $current);
        $this->WriteAttributeFloat('StaticPositionDuration', 0.0);

        $safety->Start();
        $this->WriteAttributeFloat('SafetyStartTime', microtime(true));
        $this->WriteAttributeBoolean('SafetyRunning', true);
        $this->SetTimerInterval('MovementTimer', 500);
        $this->SetValue('Status', 'Anlauf...');
    }
    public function MoveSlatTo(float $targetSlat): void
    {
        $currentSlat = $this->ReadAttributeFloat('CurrentSlatPosition');
        $currentPos = $this->ReadAttributeFloat('CurrentPosition');
        if ($targetSlat < 0.0 || $targetSlat > 100.0 || $currentSlat === $targetSlat) return;

        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');
        if ($relayUp <= 0 || $relayDown <= 0) return;

        $direction = ($targetSlat > $currentSlat) ? TrackingEngine::DIRECTION_DOWN : TrackingEngine::DIRECTION_UP;
        $slatTime = (abs($targetSlat - $currentSlat) / 100.0) * $this->ReadPropertyFloat('SlatTurnTime');
        $softTime = $this->ReadPropertyFloat('SoftStartTime');
        $totalRuntime = $slatTime + $softTime;

        $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
        $movement = new MovementEngine($this, $relay);
        if (!$movement->Start($currentPos, $currentPos, $totalRuntime)) return;

        $this->WriteAttributeInteger('CurrentDirection', $direction);
        $this->WriteAttributeFloat('TargetSlatPosition', $targetSlat);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));
        $this->WriteAttributeFloat('RemainingSlatTime', $slatTime);
        $this->WriteAttributeFloat('RemainingSoftStartTime', $softTime);
        $this->WriteAttributeBoolean('IsSlatOnlyMove', true);

        $this->SetTimerInterval('MovementTimer', 500);
        $this->SetValue('Status', 'Lamelle dreht...');
    }

    public function TriggerIntermediateShake(): void
    {
        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');
        $duration = $this->ReadPropertyFloat('IntermediateShakeDuration');
        $currentDir = $this->ReadAttributeInteger('CurrentDirection');
        if ($currentDir === 0) return;

        $shakeDirection = ($currentDir === TrackingEngine::DIRECTION_DOWN) ? TrackingEngine::DIRECTION_UP : TrackingEngine::DIRECTION_DOWN;
        $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
        if ($shakeDirection === TrackingEngine::DIRECTION_DOWN) $relay->MoveDown(); else $relay->MoveUp();

        $this->WriteAttributeInteger('OriginalDirection', $currentDir);
        $this->WriteAttributeInteger('IntermediateShakeStep', 1);
        $this->WriteAttributeFloat('IntermediateShakeRemaining', $duration);
        $this->WriteAttributeFloat('LastTimestamp', microtime(true));
        $this->SetValue('Status', 'Zwischen-Shake Phase 1...');
        $this->SetTimerInterval('MovementTimer', 500);
    }

    public function UpdateMovement(): void
    {
        $relayUp = $this->ReadPropertyInteger('RelayUp');
        $relayDown = $this->ReadPropertyInteger('RelayDown');
        if ($relayUp <= 0 || $relayDown <= 0) return;

        $direction = $this->ReadAttributeInteger('CurrentDirection');
        $target = $this->ReadAttributeFloat('TargetPosition');
        $targetSlat = $this->ReadAttributeFloat('TargetSlatPosition');
        $current = $this->ReadAttributeFloat('CurrentPosition');
        $currentSlat = $this->ReadAttributeFloat('CurrentSlatPosition');
        $lastTimestamp = $this->ReadAttributeFloat('LastTimestamp');
        $remSlat = $this->ReadAttributeFloat('RemainingSlatTime');
        $remSoft = $this->ReadAttributeFloat('RemainingSoftStartTime');
        $overrun = $this->ReadAttributeFloat('OverrunRemainingTime');
        $isSlatOnly = $this->ReadAttributeBoolean('IsSlatOnlyMove');
        $lastLoggedPos = $this->ReadAttributeFloat('LastLoggedPosition');
        $staticDuration = $this->ReadAttributeFloat('StaticPositionDuration');

        $intShakeStep = $this->ReadAttributeInteger('IntermediateShakeStep');
        $intShakeRem = $this->ReadAttributeFloat('IntermediateShakeRemaining');
        $origDir = $this->ReadAttributeInteger('OriginalDirection');

        $now = microtime(true);
        $elapsed = $now - $lastTimestamp;

        // Hardware-Abgleich mit der LCN R6 Koppelrelais-Ebene
        $activeRelayID = ($direction === TrackingEngine::DIRECTION_DOWN) ? $relayDown : $relayUp;
        if ($intShakeStep === 1) $activeRelayID = ($origDir === TrackingEngine::DIRECTION_DOWN) ? $relayUp : $relayDown;
        
        $hardwareState = false;
        if (IPS_VariableExists(IPS_GetObjectIDByIdent('Status', $activeRelayID))) {
            $hardwareState = GetValue(IPS_GetObjectIDByIdent('Status', $activeRelayID));
        }

        if (!$hardwareState && $remSoft <= 0.0) { 
            $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
            $relay->Stop();
            $this->SetTimerInterval('MovementTimer', 0);
            $this->WriteAttributeInteger('CurrentDirection', 0);
            $this->WriteAttributeInteger('ShakeFreeStep', 0);
            $this->WriteAttributeInteger('IntermediateShakeStep', 0);
            
            if ($this->ReadPropertyBoolean('IntermediateShakeEnabled') && $intShakeStep === 0 && $current > 0.5 && $current < 99.5) {
                $this->TriggerIntermediateShake();
                return;
            }
            $this->SetValue('Status', 'Standby: Hardware-Stopp');
            return;
        }

        if ($intShakeStep > 0) {
            $intShakeRem = max(0.0, $intShakeRem - $elapsed);
            $this->WriteAttributeFloat('IntermediateShakeRemaining', $intShakeRem);
            $this->WriteAttributeFloat('LastTimestamp', microtime(true));

            if ($intShakeRem <= 0.0) {
                $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
                if ($intShakeStep === 1) {
                    if ($origDir === TrackingEngine::DIRECTION_DOWN) $relay->MoveDown(); else $relay->MoveUp();
                    $this->WriteAttributeInteger('IntermediateShakeStep', 2);
                    $this->WriteAttributeFloat('IntermediateShakeRemaining', $this->ReadPropertyFloat('IntermediateShakeDuration'));
                    $this->SetValue('Status', 'Zwischen-Shake Phase 2...');
                } else {
                    $relay->Stop();
                    $this->SetTimerInterval('MovementTimer', 0);
                    $this->WriteAttributeInteger('CurrentDirection', 0);
                    $this->WriteAttributeInteger('IntermediateShakeStep', 0);
                    $this->SetValue('Status', 'Position erreicht');
                }
            }
            return; 
        }
        $isAtEndstop = ($target === 100.0 && $current >= 100.0) || ($target === 0.0 && $current <= 0.0);
        $isOverrunPhaseActive = false;

        if ($isAtEndstop && $overrun > 0.0 && !$isSlatOnly) {
            $isOverrunPhaseActive = true;
            $overrun = max(0.0, $overrun - $elapsed);
            $this->WriteAttributeFloat('OverrunRemainingTime', $overrun);
            $this->WriteAttributeFloat('LastTimestamp', microtime(true));
            $this->SetValue('Status', sprintf('Nachlauf aktiv (noch %.1fs)...', $overrun));
        } else {
            $tracking = new TrackingEngine($this, $this->ReadPropertyFloat('RuntimeUp'), $this->ReadPropertyFloat('RuntimeDown'), $remSlat, $remSoft);
            $tracking->Rehydrate($current, $currentSlat, $lastTimestamp, $remSlat, $remSoft);
            
            if ($isSlatOnly) {
                if ($remSoft <= 0.0 && $remSlat > 0.0) {
                    $allocatedTime = min($elapsed, $remSlat);
                    $remSlat -= $allocatedTime;
                    $slatDelta = ($allocatedTime / max(0.1, (float)$this->ReadPropertyFloat('SlatTurnTime'))) * 100.0;
                    $currentSlat = ($direction === TrackingEngine::DIRECTION_DOWN) ? ($currentSlat + $slatDelta) : ($currentSlat - $slatDelta);
                }
                $remSoft = max(0.0, $remSoft - $elapsed);
                $newPos = $current;
                $newSlat = min(100.0, max(0.0, $currentSlat));
            } else {
                $tracking->Move($direction);
                $newPos = $tracking->GetPosition();
                $newSlat = $tracking->GetSlatPosition();
            }
            
            $this->WriteAttributeFloat('CurrentPosition', $newPos);
            $this->WriteAttributeFloat('CurrentSlatPosition', $newSlat); 
            $this->WriteAttributeFloat('LastTimestamp', microtime(true));
            $this->WriteAttributeFloat('RemainingSlatTime', $remSlat);
            $this->WriteAttributeFloat('RemainingSoftStartTime', $remSoft);
            
            $this->SetValue('Position', (int)$newPos);
            $this->SetValue('SlatPosition', (int)$newSlat); 
            $current = $newPos;
        }

        $isFahrtAktiv = ($remSoft > 0.0 || $remSlat > 0.0 || (!$isAtEndstop && !$isSlatOnly && abs($current - $target) > 0.5));
        if (!$isFahrtAktiv && (abs($current - $lastLoggedPos) < 0.01 || $isOverrunPhaseActive || $isSlatOnly)) {
            $staticDuration += $elapsed;
        } else {
            $staticDuration = 0.0; 
        }
        
        $this->WriteAttributeFloat('LastLoggedPosition', $current);
        $this->WriteAttributeFloat('StaticPositionDuration', $staticDuration);

        // Intelligenter Ruhezustand (Standby) nach exakt 5s kalkuliertem Stillstand
        if ($staticDuration >= 5.0) {
            $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
            $relay->Stop(); 
            $this->SetTimerInterval('MovementTimer', 0);
            $this->WriteAttributeInteger('CurrentDirection', 0);
            $this->WriteAttributeInteger('ShakeFreeStep', 0);
            
            if ($this->ReadPropertyBoolean('IntermediateShakeEnabled') && !$isAtEndstop && !$isSlatOnly) {
                $this->TriggerIntermediateShake();
                return;
            }

            if (!$isSlatOnly && ($target === 100.0 || $current >= 99.5)) {
                $this->ReferenceClosed();
            } elseif (!$isSlatOnly && ($target === 0.0 || $current <= 0.5)) {
                $this->WriteAttributeFloat('CurrentPosition', 0.0);
                $this->WriteAttributeFloat('CurrentSlatPosition', 0.0);
                $this->SetValue('Position', 0);
                $this->SetValue('SlatPosition', 0);
                $this->SetValue('Status', 'Standby: Oben (0%)');
            } else {
                $this->SetValue('Status', 'Standby: Position erreicht');
            }
            return;
        }

        if ($remSoft > 0.0) $this->SetValue('Status', 'Sanftanlauf...');
        elseif ($remSlat > 0.0) $this->SetValue('Status', 'Lamellenwendung...');
        elseif ($overrun > 0.0 && !$isSlatOnly) $this->SetValue('Status', 'Nachlauf aktiv...');
        else $this->SetValue('Status', 'Fahrt läuft');

        $isTargetReached = $isSlatOnly ? ($remSlat <= 0.0 && $remSoft <= 0.0) : ((abs($current - $target) <= 0.5 && $overrun <= 0.0) || ($direction === TrackingEngine::DIRECTION_UP && $current <= 0.0 && $overrun <= 0.0) || ($direction === TrackingEngine::DIRECTION_DOWN && $current >= 100.0 && $overrun <= 0.0));

        if ($isTargetReached) {
            if ($this->ReadPropertyBoolean('IntermediateShakeEnabled') && !$isAtEndstop && !$isSlatOnly) {
                $this->TriggerIntermediateShake();
                return;
            }

            $relay = new RelayEngine($this, $relayUp, $relayDown, $this->ReadPropertyInteger('SwitchPause'));
            $relay->Stop();
            
            if ($isSlatOnly) {
                $this->WriteAttributeFloat('CurrentSlatPosition', $targetSlat);
                $this->SetValue('SlatPosition', (int)$targetSlat);
                $this->SetTimerInterval('MovementTimer', 0);
                $this->WriteAttributeInteger('CurrentDirection', 0);
                $this->SetValue('Status', 'Lamelle eingestellt');
            } else {
                if ($target === 100.0) {
                    $this->ReferenceClosed();
                } elseif ($target === 0.0) {
                    $this->WriteAttributeFloat('CurrentPosition', 0.0);
                    $this->WriteAttributeFloat('CurrentSlatPosition', 0.0);
                    $this->SetValue('Position', 0);
                    $this->SetValue('SlatPosition', 0);
                    $this->SetTimerInterval('MovementTimer', 0);
                    $this->WriteAttributeInteger('CurrentDirection', 0);
                    $this->SetValue('Status', 'Referenz geöffnet gespeichert (0%)');
                } else {
                    $this->WriteAttributeFloat('CurrentPosition', $target);
                    $this->SetValue('Position', (int)$target);
                    $finalSlat = ($direction === TrackingEngine::DIRECTION_DOWN) ? 100.0 : 0.0;
                    $this->WriteAttributeFloat('CurrentSlatPosition', $finalSlat);
                    $this->SetValue('SlatPosition', (int)$finalSlat);
                    $this->SetTimerInterval('MovementTimer', 0);
                    $this->WriteAttributeInteger('CurrentDirection', 0);
                    $this->SetValue('Status', 'Position erreicht');
                }

                $coldStep = $this->ReadAttributeInteger('ShakeFreeStep');
                if ($target === 100.0 && $coldStep > 0) $this->AdvanceShakeFreeSequence(100.0); 
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
        $this->WriteAttributeFloat('CurrentPosition', 100.0);
        $this->WriteAttributeFloat('CurrentSlatPosition', 100.0); 
        $this->SetValue('Position', 100);
        $this->SetValue('SlatPosition', 100);
        $this->SetTimerInterval('MovementTimer', 0);
        $this->WriteAttributeInteger('CurrentDirection', 0);
        $this->SetValue('Status', 'Referenz geschlossen gespeichert (100%)');
    }

    public function GetModuleInstanceID(): int
    {
        return $this->InstanceID;
    }
}
