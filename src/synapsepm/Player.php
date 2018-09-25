<?php

declare(strict_types=1);

namespace synapsepm;

use pocketmine\level\Level;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\FullChunkDataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\Player as PMPlayer;
use pocketmine\utils\UUID;
use synapsepm\event\player\PlayerConnectEvent;
use synapsepm\network\protocol\spp\PlayerLoginPacket;
use synapsepm\network\protocol\spp\TransferPacket;
use synapsepm\network\SynLibInterface;
use synapsepm\utils\DataPacketEidReplacer;

class Player extends PMPlayer {
    /** @var Synapse */
    private $synapse;
    private $isFirstTimeLogin = false;
    private $lastPacketTime;
    /** @var UUID */
    private $overrideUUID;

    /** @var SynLibInterface $interface */
    private $session;

    public function __construct(SynLibInterface $interface, NetworkSession $session){
        parent::__construct($this->getServer(), $session);
        $this->synapse = $interface->getSynapse();
        $this->session = $session;
    }

    public function handleLoginPacket(PlayerLoginPacket $packet){
        $this->isFirstTimeLogin = $packet->isFirstTime;
        $this->server->getPluginManager()->callEvent($ev = new PlayerConnectEvent($this, $this->isFirstTimeLogin));
        $loginPacket = $this->synapse->getPacket($packet->cachedLoginPacket);

        if($loginPacket === null){
            $this->close($this->getLeaveMessage(), 'Invalid login packet');
            return;
        }

        $this->handleDataPacket($loginPacket);
        $this->uuid = $this->overrideUUID;
        $this->rawUUID = $this->uuid->toBinary();
    }

    /**
     * @internal
     *
     * Unload all old chunks(send empty)
     */
    public function forceSendEmptyChunks(){
        foreach($this->usedChunks as $index => $true){
            Level::getXZ($index, $chunkX, $chunkZ);
            $pk = new FullChunkDataPacket();
            $pk->chunkX = $chunkX;
            $pk->chunkZ = $chunkZ;
            $pk->data = '';
            $this->sendDataPacket($pk);
        }
    }

    public function handleDataPacket(DataPacket $packet){
        $this->lastPacketTime = microtime(true);

        if($packet->pid() == ProtocolInfo::MOVE_PLAYER_PACKET && $this->id === null){
            //            $pk = new MovePlayerPacket();
            //            $pk->entityRuntimeId = PHP_INT_MAX;
            //            $pk->position = $this->getOffsetPosition($this);
            //            $pk->pitch = $this->pitch;
            //            $pk->headYaw = $this->yaw;
            //            $pk->yaw = $this->yaw;
            //            $pk->mode = MovePlayerPacket::MODE_RESET;
            //
            //            $this->interface->putPacket($this, $pk, false, false);
            return;
        }

        parent::sendDataPacket($packet);
    }

    public function onUpdate(int $currentTick): bool{
        if((microtime(true) - $this->lastPacketTime) >= 5 * 60){
            $this->close('', 'timeout');

            return false;
        }
        return parent::onUpdate($currentTick);
    }

    public function getUniqueId(): UUID{
        return $this->overrideUUID ?? parent::getUniqueId();
    }

    public function setUniqueId(UUID $uuid){
        $this->uuid = $uuid;
        $this->overrideUUID = $uuid;
    }

    protected function processPacket(DataPacket $packet): bool{
        if(!$this->isFirstTimeLogin){
            if($packet instanceof PlayStatusPacket && $packet->status === PlayStatusPacket::PLAYER_SPAWN){
                return true;
            }

            if($packet instanceof ResourcePacksInfoPacket){
                $this->completeLoginSequence();
                return true;
            }

            if($packet instanceof StartGamePacket){
                return true;
            }
        } else{
            if($packet instanceof StartGamePacket){
                $packet->entityUniqueId = PHP_INT_MAX;
                $packet->entityRuntimeId = PHP_INT_MAX;
            }
        }

        //$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
        //return $ev->isCancelled();
        return false;
    }

    public function sendDataPacket(DataPacket $packet, bool $needACK = \false, bool $immediate = \false): bool{
        if(!$this->processPacket($packet)){
            if($this->id != null){
                $packet = DataPacketEidReplacer::replace($packet, $this->getId(), PHP_INT_MAX);
            }

            return parent::sendDataPacket($packet, $immediate);
        }

        return false;
    }

    public function broadcastEntityEvent(int $eventId, ?int $eventData = \null, ?array $players = \null): void{
        $pk = new EntityEventPacket();
        $pk->entityRuntimeId = $this->id;
        $pk->event = $eventId;
        $pk->data = $eventData ?? 0;

        if($players === null){
            $players = $this->getViewers();

            if($this->spawned){
                $this->sendDataPacket($pk);
            }
        }

        $this->server->broadcastPacket($players, $pk);
    }

    protected function completeLoginSequence(){
        $r = parent::_actuallyConstruct();

        $this->sendGamemode();
        $this->setViewDistance($this->server->getViewDistance()); //TODO: save view distance in nemisys

        return $r;
    }

    public function isFirstLogin(){
        return $this->isFirstTimeLogin;
    }

    public function getSynapse(): Synapse{
        return $this->synapse;
    }

    public function synapseTransferByDesc(string $desc): bool{
        return $this->synapseTransfer($this->synapse->getHashByDescription($desc) ?? "");
    }

    public function synapseTransfer(string $hash): bool{
        if($this->synapse->getHash() === $hash){
            return false;
        }

        $clients = $this->synapse->getClientData();

        if(!isset($clients[$hash])){
            return false;
        }

        foreach($this->getEffects() as $effect){
            $pk = new MobEffectPacket();
            $pk->entityRuntimeId = $this->getId();
            $pk->eventId = MobEffectPacket::EVENT_REMOVE;
            $pk->effectId = $effect->getId();
            $this->sendDataPacket($pk);
        }

        foreach($this->getAttributeMap()->getAll() as $attribute){
            $attribute->resetToDefault();
        }

        $this->sendAttributes(true);
        $this->setSprinting(false);
        $this->setSneaking(false);
        $this->setSwimming(false);

        $this->forceSendEmptyChunks();

        $transferPacket = new TransferPacket();
        $transferPacket->uuid = $this->getUniqueId();
        $transferPacket->clientHash = $hash;
        $this->synapse->sendDataPacket($transferPacket);

        return true;
    }
}