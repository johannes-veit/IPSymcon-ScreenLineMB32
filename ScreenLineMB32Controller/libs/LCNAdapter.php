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
            return false;
        }

        LCN_SwitchRelay(
            $outputID,
            $state
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