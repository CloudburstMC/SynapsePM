<?php

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\UUID;

class PlayerLoginPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::PLAYER_LOGIN_PACKET;

    /** @var UUID */
    public $uuid;
    public $address;
    public $port;
    public $isFirstTime;
    public $cachedLoginPacket;

    public function encode(): void{
        $this->reset();
        $this->putUUID($this->uuid);
        $this->putString($this->address);
        $this->putInt($this->port);
        $this->putBool($this->isFirstTime);
        $this->putInt(strlen($this->cachedLoginPacket));
        $this->put($this->cachedLoginPacket);
    }

    public function decode(): void{
        $this->uuid = $this->getUUID();
        $this->address = $this->getString();
        $this->port = $this->getInt();
        $this->isFirstTime = $this->getBool();
        $this->cachedLoginPacket = $this->get($this->getInt());
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}