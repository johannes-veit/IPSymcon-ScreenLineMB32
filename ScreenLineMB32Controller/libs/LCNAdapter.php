<?php

declare(strict_types=1);

final class LCNAdapter
{
    private int $instanceID;

    public function __construct(int $instanceID) 
    {
        $this->instanceID = $instanceID;
    }

    public function SetOutput(int $outputID, bool $state): bool 
    {
        if ($outputID <= 0 || !IPS_InstanceExists($outputID)) {
            return false;
        }

        try {
            LCN_SwitchRelay($outputID, $state);
            IPS_SendDebug($this->instanceID, 'LCNAdapter', sprintf('Relais %d -> %s', $outputID, $state ? 'AN' : 'AUS'), 0);
            return true;
        } catch (Throwable $e) {
            IPS_SendDebug($this->instanceID, 'LCNAdapter', 'Fehler: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function AllOff(int $relayUp, int $relayDown): void 
    {
        if ($relayUp > 0 && IPS_InstanceExists($relayUp)) {
            try { LCN_SwitchRelay($relayUp, false); } catch (Throwable $e) {}
        }
        if ($relayDown > 0 && IPS_InstanceExists($relayDown)) {
            try { LCN_SwitchRelay($relayDown, false); } catch (Throwable $e) {}
        }
    }
}
