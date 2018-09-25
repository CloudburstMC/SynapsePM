<?php

declare(strict_types=1);

namespace synapsepm\network;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\NetworkInterface;
use synapsepm\network\protocol\spp\RedirectPacket;
use synapsepm\Synapse;

class SynLibInterface implements NetworkInterface {
    private $synapseInterface;
    private $synapse;

    public function __construct(Synapse $synapse, SynapseInterface $interface) {
        $this->synapse = $synapse;
        $this->synapseInterface = $interface;
    }

    public function start() : void{
    }

    public function getSynapse(): Synapse {
        return $this->synapse;
    }

    public function emergencyShutdown() : void{
    }

    public function setName(string $name) : void{
    }

    public function process(): void {
    }

    public function close(NetworkSession $session, string $reason = "unknown reason") : void{
    }

    public function putPacket(NetworkSession $session, string $payload, bool $immediate = true) : void{
        $player = $session->getPlayer();
        if (!$player->isClosed()) {
            $pk = new RedirectPacket();
            $pk->uuid = $player->getUniqueId();
            $pk->direct = $immediate;
            if (!$pk->isEncoded) {
                $pk->encode();
            }
            $pk->mcpeBuffer = $pk->buffer;
            $this->synapseInterface->putPacket($pk);
        }
    }

    public function shutdown() : void{
    }

    public function tick(): void{
    }
}