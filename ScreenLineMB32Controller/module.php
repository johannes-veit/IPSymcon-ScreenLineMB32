<?php

class ScreenLineMB32Controller extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger(
            'ModuleID',
            0
        );

        $this->RegisterPropertyInteger(
            'MotorRuntime',
            30
        );

        $this->RegisterPropertyBoolean(
            'EnableSafety',
            true
        );

        $this->RegisterPropertyBoolean(
            'EnableAdvanced',
            false
        );
    }


    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetSummary(
            'ScreenLine MB32 Controller'
        );
    }


    public function TestMovement()
    {
        $this->SendDebug(
            'ScreenLine',
            'Testbewegung gestartet',
            0
        );
    }


    private function SendCommand($command)
    {
        $this->SendDebug(
            'Command',
            $command,
            0
        );
    }
}