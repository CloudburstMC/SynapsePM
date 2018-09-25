<?php

/**
 * @author CreeperFace
 */

declare(strict_types=1);

namespace synapsepm\network\protocol\spp;

use pocketmine\network\mcpe\handler\SessionHandler;
use pocketmine\network\mcpe\protocol\DataPacket;

class PluginMessagePacket extends DataPacket {

    const NETWORK_ID = SynapseInfo::PLUGIN_MESSAGE_PACKET;

    public $channel;
    public $data;

    public function encode() : void{
        $this->reset();
        $this->putString($this->channel);
        $this->putString($this->data);
    }

    public function decode() : void{
        $this->channel = $this->getString();
        $this->data = $this->getString();
    }

    public function handle(SessionHandler $handler): bool{
        return false;
    }
}