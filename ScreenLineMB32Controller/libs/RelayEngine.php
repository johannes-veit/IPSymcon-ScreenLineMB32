<?php

declare(strict_types=1);

final class RelayEngine
{
    private LCNAdapter $lcn;
    private int $relayUp;
    private int $relayDown;
    private int $switchPause;

    public function __construct(int $instanceID, int $relayUp, int $relayDown, int $switchPause = 400) {
        $this->lcn = new LCNAdapter($instanceID);
        $this->relayUp = $relayUp;
        $this->relayDown = $relayDown;
        $this->switchPause = $switchPause;
    }

    public function MoveUp(): bool
    {
        if ($this->relayUp <= 0 || $this->relayUp === $this->relayDown) return false;
        $this->Stop();
        if ($this->switchPause > 0) IPS_Sleep($this->switchPause);
        return $this->lcn->SetOutput($this->relayUp, true);
    }

    public function MoveDown(): bool
    {
        if ($this->relayDown <= 0 || $this->relayUp === $this->relayDown) return false;
        $this->Stop();
        if ($this->switchPause > 0) IPS_Sleep($this->switchPause);
        return $this->lcn->SetOutput($this->relayDown, true);
    }

    public function Stop(): void
    {
        $this->lcn->AllOff($this->relayUp, $this->relayDown);
    }
}
