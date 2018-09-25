<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\UUID;

class RedirectPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::REDIRECT_PACKET;
    /** @var UUID */
    public $uuid;
    public $direct;
    public $mcpeBuffer;

    public function encode() : void{
        $this->reset();
        $this->putUUID($this->uuid);
        $this->putBool($this->direct);
        $this->putUnsignedVarInt(strlen($this->mcpeBuffer));
        $this->put($this->mcpeBuffer);
    }

    public function decode() : void{
        $this->uuid = $this->getUUID();
        $this->direct = $this->getBool();
        $this->mcpeBuffer = $this->get($this->getUnsignedVarInt());
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}