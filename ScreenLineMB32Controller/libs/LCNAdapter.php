<?php

declare(strict_types=1);

final class LCNAdapter
{
    private IPSModule $module;

    public function __construct(
        IPSModule $module
    ) {
        $this->module = $module;
    }

    public function SetOutput(
        int $statusVariableID,
        bool $state
    ): bool {

        if ($statusVariableID <= 0) {

            $this->module->SendDebug(
                'LCNAdapter',
                'Ungültige Statusvariablen-ID',
                0
            );

            return false;
        }

        if (!IPS_VariableExists($statusVariableID)) {

            $this->module->SendDebug(
                'LCNAdapter',
                'Statusvariable existiert nicht',
                0
            );

            return false;
        }

        try {

            RequestAction(
                $statusVariableID,
                $state
            );

        } catch (Throwable $e) {

            $this->module->SendDebug(
                'LCNAdapter',
                $e->getMessage(),
                0
            );

            return false;
        }

        $this->module->SendDebug(
            'LCNAdapter',
            sprintf(
                'Statusvariable %d -> %s',
                $statusVariableID,
                $state ? 'EIN' : 'AUS'
            ),
            0
        );

        return true;
    }

    public function AllOff(
        int $relayUpStatusID,
        int $relayDownStatusID
    ): void {

        $this->SetOutput(
            $relayUpStatusID,
            false
        );

        $this->SetOutput(
            $relayDownStatusID,
            false
        );
    }
}