<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\UUID;

class BroadcastPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::BROADCAST_PACKET;

    /** @var UUID[] */
    public $entries = [];
    public $direct;
    public $payload;

    public function encode() : void{
        $this->reset();
        $this->putBool($this->direct);
        $this->putShort(count($this->entries));
        foreach ($this->entries as $uuid) {
            $this->putUUID($uuid);
        }
        $this->putString($this->payload);
    }

    public function decode() : void{
        $this->direct = $this->getBool();
        $len = $this->getShort();
        for ($i = 0; $i < $len; $i++) {
            $this->entries[] = $this->getUUID();
        }
        $this->payload = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}
