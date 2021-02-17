<?php
declare(strict_types=1);

namespace synapsepm;

use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\Player as PMPlayer;
use pocketmine\utils\UUID;
use synapsepm\event\player\PlayerConnectEvent;
use synapsepm\network\protocol\spp\PlayerLoginPacket;
use synapsepm\network\protocol\spp\TransferPacket;
use synapsepm\network\SynapsePlayerNetworkSessionAdapter;
use synapsepm\network\SynLibInterface;
use synapsepm\utils\DataPacketEidReplacer;


class Player extends PMPlayer {
    /** @var Synapse */
    private $synapse;
    private $isFirstTimeLogin = false;
    private $lastPacketTime;
    /** @var UUID */
    private $overrideUUID;

    public function __construct(SynLibInterface $interface, $ip, $port) {
        parent::__construct($interface, $ip, $port);
        $this->synapse = $interface->getSynapse();

        // Override original one
        $this->sessionAdapter = new SynapsePlayerNetworkSessionAdapter($this->server, $this);
    }

    public function handleLoginPacket(PlayerLoginPacket $packet) {
        $this->isFirstTimeLogin = $packet->isFirstTime;
        (new PlayerConnectEvent($this, $this->isFirstTimeLogin))->call();

        $loginPacket = $this->synapse->getPacket($packet->cachedLoginPacket);

        if ($loginPacket === null) {
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
    public function forceSendEmptyChunks() {
        foreach ($this->usedChunks as $index => $true) {
            Level::getXZ($index, $chunkX, $chunkZ);

            $pk = LevelChunkPacket::withoutCache($chunkX, $chunkZ, 16, '');
            $this->dataPacket($pk);
        }
    }

    public function handleDataPacket(DataPacket $packet) {
        $this->lastPacketTime = microtime(true);

        if ($packet->pid() == ProtocolInfo::MOVE_PLAYER_PACKET && empty($this->id)) {
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

        parent::handleDataPacket($packet);
    }

    public function onUpdate(int $currentTick): bool {
        if ((microtime(true) - $this->lastPacketTime) >= 5 * 60) {
            $this->close('', 'timeout');

            return false;
        }
        return parent::onUpdate($currentTick);
    }

    public function getUniqueId(): UUID {
        return $this->overrideUUID ?? parent::getUniqueId();
    }

    public function setUniqueId(UUID $uuid) {
        $this->uuid = $uuid;
        $this->overrideUUID = $uuid;
    }

    protected function processPacket(DataPacket $packet): bool {
        if (!$this->isFirstTimeLogin) {
            if (!$this->spawned && $packet instanceof PlayStatusPacket && $packet->status === PlayStatusPacket::PLAYER_SPAWN) {
                return true;
            }

            if ($packet instanceof ResourcePacksInfoPacket) {
                $this->completeLoginSequence();
                return true;
            }

            if ($packet instanceof StartGamePacket || $packet instanceof AvailableActorIdentifiersPacket) {
                return true;
            }
        } else {
            if ($packet instanceof StartGamePacket) {
                $packet->entityUniqueId = PHP_INT_MAX;
                $packet->entityRuntimeId = PHP_INT_MAX;
            }
        }

//        $this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
//        return $ev->isCancelled();
        return false;
    }

    public function sendDataPacket(DataPacket $packet, bool $needACK = \false, bool $immediate = \false) {
        if (!$this->processPacket($packet)) {
            if ($this->id != null) {
                $packet = DataPacketEidReplacer::replace($packet, $this->getId(), PHP_INT_MAX);
            }

            return parent::sendDataPacket($packet, $needACK, $immediate);
        }

        return false;
    }

    public function broadcastEntityEvent(int $eventId, ?int $eventData = \null, ?array $players = \null): void {
        $pk = new ActorEventPacket();
        $pk->entityRuntimeId = $this->id;
        $pk->event = $eventId;
        $pk->data = $eventData ?? 0;

        if ($players === \null) {
            $players = $this->getViewers();

            if ($this->spawned) {
                $this->dataPacket($pk);
            }
        }

        $this->server->broadcastPacket($players, $pk);
    }

    protected function broadcastMotion(): void {
        $pk = new SetActorMotionPacket();
        $pk->entityRuntimeId = PHP_INT_MAX;
        $pk->motion = $this->getMotion();

        $this->sendDataPacket($pk);

        parent::broadcastMotion();
    }

    public function broadcastMovement(bool $teleport = false): void
    {
        $pk = new MoveActorAbsolutePacket();
        $pk->entityRuntimeId = PHP_INT_MAX;
        $pk->position = $this->getOffsetPosition($this);

        //this looks very odd but is correct as of 1.5.0.7
        //for arrows this is actually x/y/z rotation
        //for mobs x and z are used for pitch and yaw, and y is used for headyaw
        $pk->xRot = $this->pitch;
        $pk->yRot = $this->yaw; //TODO: head yaw
        $pk->zRot = $this->yaw;

        if ($teleport) {
            $pk->flags |= MoveActorAbsolutePacket::FLAG_TELEPORT;
        }

        $this->sendDataPacket($pk);

        parent::broadcastMovement($teleport);
    }

    public function handleEntityEvent(ActorEventPacket $packet): bool {
        if (!$this->spawned or !$this->isAlive()) {
            return true;
        }
        $this->doCloseInventory();

        switch ($packet->event) {
            case ActorEventPacket::EATING_ITEM:
                if ($packet->data === 0) {
                    return false;
                }

                $broadcastPacket = clone $packet;
                $broadcastPacket->entityRuntimeId = PHP_INT_MAX;
                $broadcastPacket->isEncoded = false;

                $this->dataPacket($packet);
                $this->server->broadcastPacket($this->getViewers(), $broadcastPacket);
                break;
            default:
                return parent::handleEntityEvent($packet);
        }

        return true;
    }

    protected function completeLoginSequence() {
        parent::completeLoginSequence();

        $this->sendGamemode();
        $this->setViewDistance($this->server->getViewDistance()); //TODO: save view distance in nemisys

        if (!$this->isFirstTimeLogin) {
            $this->doFirstSpawn();
        }
    }

    public function isFirstLogin() {
        return $this->isFirstTimeLogin;
    }

    public function getSynapse(): Synapse {
        return $this->synapse;
    }

    public function synapseTransferByDesc(string $desc): bool {
        return $this->synapseTransfer($this->synapse->getHashByDescription($desc) ?? "");
    }

    public function synapseTransfer(string $hash): bool {
        if ($this->synapse->getHash() === $hash) {
            return false;
        }

        $clients = $this->synapse->getClientData();

        if (!isset($clients[$hash])) {
            return false;
        }

        foreach ($this->getEffects() as $effect) {
            $pk = new MobEffectPacket();
            $pk->entityRuntimeId = $this->getId();
            $pk->eventId = MobEffectPacket::EVENT_REMOVE;
            $pk->effectId = $effect->getId();
            $this->dataPacket($pk);
        }

        foreach ($this->getAttributeMap()->getAll() as $attribute) {
            $attribute->resetToDefault();
        }

        $this->sendAttributes(true);
        $this->setSprinting(false);
        $this->setSneaking(false);

        // $this->forceSendEmptyChunks();
        $this->setImmobile();

        $transferPacket = new TransferPacket();
        $transferPacket->uuid = $this->getUniqueId();
        $transferPacket->clientHash = $hash;
        $this->synapse->sendDataPacket($transferPacket);

        return true;
    }
}
