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
        if ($outputID <= 0) {
            return false;
        }

        if (!IPS_InstanceExists($outputID)) {
            IPS_SendDebug($this->instanceID, 'LCNAdapter', 'Fehler: Instanz ID ' . $outputID . ' existiert nicht.', 0);
            return false;
        }

        try {
            LCN_SwitchRelay($outputID, $state);
            IPS_SendDebug($this->instanceID, 'LCNAdapter', sprintf('Instanz %d auf %s gesetzt', $outputID, $state ? 'AN' : 'AUS'), 0);
            return true;
        } catch (Throwable $e) {
            IPS_SendDebug($this->instanceID, 'LCNAdapter', 'Fehler beim Schalten: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function AllOff(int $relayUp, int $relayDown): void 
    {
        if ($relayUp > 0 && IPS_InstanceExists($relayUp)) {
            try {
                LCN_SwitchRelay($relayUp, false);
            } catch (Throwable $e) {
                // Fehler abfangen
            }
        }

        if ($relayDown > 0 && IPS_InstanceExists($relayDown)) {
            try {
                LCN_SwitchRelay($relayDown, false);
            } catch (Throwable $e) {
                // Fehler abfangen
            }
        }
        
        IPS_SendDebug($this->instanceID, 'LCNAdapter', 'AllOff aufgerufen: Beide Richtungen abgeschaltet.', 0);
    }
}
