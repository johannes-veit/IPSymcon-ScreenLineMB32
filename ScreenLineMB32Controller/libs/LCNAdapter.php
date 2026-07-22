<?php

declare(strict_types=1);

final class LCNAdapter
{
    private IPSModule $module;

    public function __construct(IPSModule $module) 
    {
        $this->module = $module;
    }

    public function SetOutput(int $outputID, bool $state): bool 
    {
        if ($outputID <= 0) {
            return false;
        }

        if (!IPS_InstanceExists($outputID)) {
            $this->module->SendDebug('LCNAdapter', 'Fehler: Instanz ID ' . $outputID . ' existiert nicht.', 0);
            return false;
        }

        try {
            LCN_SwitchRelay($outputID, $state);
            $this->module->SendDebug('LCNAdapter', sprintf('Instanz %d auf %s gesetzt', $outputID, $state ? 'AN' : 'AUS'), 0);
            return true;
        } catch (Throwable $e) {
            $this->module->SendDebug('LCNAdapter', 'Fehler beim Schalten: ' . $e->getMessage(), 0);
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
        
        $this->module->SendDebug('LCNAdapter', 'AllOff aufgerufen: Beide Richtungen abgeschaltet.', 0);
    }
}
