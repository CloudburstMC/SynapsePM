<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;

class InformationPacket extends DataPacket {

    const NETWORK_ID = SynapseInfo::INFORMATION_PACKET;

    const TYPE_LOGIN = 0;
    const TYPE_CLIENT_DATA = 1;
    const INFO_LOGIN_SUCCESS = 'success';
    const INFO_LOGIN_FAILED = 'failed';

    public $type;
    public $message;

    public function encode(): void{
        $this->reset();
        $this->putByte($this->type);
        $this->putString($this->message);
    }

    public function decode(): void{
        $this->type = $this->getByte();
        $this->message = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}