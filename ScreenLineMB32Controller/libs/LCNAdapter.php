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
        int $outputID,
        bool $state
    ): bool {

        if ($outputID <= 0) {

            $this->module->SendDebug(
                'LCNAdapter',
                'Ungültige Ausgangs-ID',
                0
            );

            return false;
        }

        LCN_SwitchRelay(
            $outputID,
            $state
        );

        $this->module->SendDebug(
            'LCNAdapter',
            sprintf(
                'Relais %d -> %s',
                $outputID,
                $state ? 'EIN' : 'AUS'
            ),
            0
        );

        return true;
    }

    public function AllOff(
        int $relayUp,
        int $relayDown
    ): void {

        if ($relayUp > 0) {
            LCN_SwitchRelay(
                $relayUp,
                false
            );
        }

        if ($relayDown > 0) {
            LCN_SwitchRelay(
                $relayDown,
                false
            );
        }
    }
}