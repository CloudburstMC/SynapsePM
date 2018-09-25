<?php
declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;

class HeartbeatPacket extends DataPacket {
    const NETWORK_ID = SynapseInfo::HEARTBEAT_PACKET;

    public $tps;
    public $load;
    public $upTime;

    public function encode() : void{
        $this->reset();
        $this->putFloat($this->tps);
        $this->putFloat($this->load);
        $this->putLong($this->upTime);
    }

    public function decode() : void{
        $this->tps = $this->getFloat();
        $this->load = $this->getFloat();
        $this->upTime = $this->getLong();
    }

    public function handle(SessionHandler $handler): bool{
        return true;
    }
}