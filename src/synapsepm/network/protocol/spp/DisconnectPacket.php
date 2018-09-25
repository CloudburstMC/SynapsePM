<?php
declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;

class DisconnectPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::DISCONNECT_PACKET;
    const TYPE_WRONG_PROTOCOL = 0;
    const TYPE_GENERIC = 1;

    public $type;
    public $message;

    public function encode() : void{
        $this->reset();
        $this->putByte($this->type);
        $this->putString($this->message);
    }

    public function decode() : void{
        $this->type = $this->getByte();
        $this->message = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}