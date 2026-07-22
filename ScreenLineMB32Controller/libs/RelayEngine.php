<?php

declare(strict_types=1);

final class RelayEngine
{
    private IPSModule $module;

    private LCNAdapter $lcn;

    private int $relayUpStatusID;

    private int $relayDownStatusID;

    private int $switchPause;


    public function __construct(
        IPSModule $module,
        int $relayUpStatusID,
        int $relayDownStatusID,
        int $switchPause = 400
    ) {

        $this->module = $module;

        $this->lcn = new LCNAdapter(
            $module
        );

        $this->relayUpStatusID = $relayUpStatusID;

        $this->relayDownStatusID = $relayDownStatusID;

        $this->switchPause = $switchPause;
    }


    public function MoveUp(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->Stop();

        usleep(
            $this->switchPause * 1000
        );

        return $this->lcn->SetOutput(
            $this->relayUpStatusID,
            true
        );
    }


    public function MoveDown(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->Stop();

        usleep(
            $this->switchPause * 1000
        );

        return $this->lcn->SetOutput(
            $this->relayDownStatusID,
            true
        );
    }


    public function Stop(): void
    {
        $this->lcn->AllOff(
            $this->relayUpStatusID,
            $this->relayDownStatusID
        );
    }


    public function Validate(): bool
    {
        if ($this->relayUpStatusID <= 0) {

            $this->module->SendDebug(
                'RelayEngine',
                'AUF Statusvariable fehlt',
                0
            );

            return false;
        }

        if ($this->relayDownStatusID <= 0) {

            $this->module->SendDebug(
                'RelayEngine',
                'AB Statusvariable fehlt',
                0
            );

            return false;
        }

        if ($this->relayUpStatusID === $this->relayDownStatusID) {

            $this->module->SendDebug(
                'RelayEngine',
                'AUF und AB Statusvariable identisch',
                0
            );

            return false;
        }

        return true;
    }
}