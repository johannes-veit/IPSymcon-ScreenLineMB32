<?php

declare(strict_types=1);

final class RelayEngine
{
    private IPSModule $module;
    private LCNAdapter $lcn;
    private int $relayUp;
    private int $relayDown;
    private int $switchPause;

    public function __construct(
        IPSModule $module,
        int $relayUp,
        int $relayDown,
        int $switchPause = 400
    ) {
        $this->module = $module;
        $this->lcn = new LCNAdapter($module);
        $this->relayUp = $relayUp;
        $this->relayDown = $relayDown;
        $this->switchPause = $switchPause;
    }

    public function MoveUp(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->Stop();

        if ($this->switchPause > 0) {
            $this->module->SendDebug('RelayEngine', sprintf('Warte Umschaltpause: %d ms', $this->switchPause), 0);
            IPS_Sleep($this->switchPause);
        }

        return $this->lcn->SetOutput($this->relayUp, true);
    }

    public function MoveDown(): bool
    {
        if (!$this->Validate()) {
            return false;
        }

        $this->Stop();

        if ($this->switchPause > 0) {
            $this->module->SendDebug('RelayEngine', sprintf('Warte Umschaltpause: %d ms', $this->switchPause), 0);
            IPS_Sleep($this->switchPause);
        }

        return $this->lcn->SetOutput($this->relayDown, true);
    }

    public function Stop(): void
    {
        $this->lcn->AllOff($this->relayUp, $this->relayDown);
    }

    private function Validate(): bool
    {
        if ($this->relayUp <= 0 || $this->relayDown <= 0) {
            return false;
        }

        return $this->relayUp !== $this->relayDown;
    }
}
