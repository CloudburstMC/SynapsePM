<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\UUID;

class PlayerLogoutPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::PLAYER_LOGOUT_PACKET;

    /** @var UUID */
    public $uuid;
    public $reason;

    public function encode(): void{
        $this->reset();
        $this->putUUID($this->uuid);
        $this->putString($this->reason);
    }

    public function decode(): void{
        $this->uuid = $this->getUUID();
        $this->reason = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}