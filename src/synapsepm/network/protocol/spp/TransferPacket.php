<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\UUID;

class TransferPacket extends DataPacket {

    const NETWORK_ID = SynapseInfo::TRANSFER_PACKET;

    /** @var UUID */
    public $uuid;
    public $clientHash;

    public function encode(): void{
        $this->reset();
        $this->putUUID($this->uuid);
        $this->putString($this->clientHash);
    }

    public function decode(): void{
        $this->uuid = $this->getUUID();
        $this->clientHash = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}