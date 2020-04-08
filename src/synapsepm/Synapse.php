<?php
declare(strict_types=1);

namespace synapsepm;

use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use synapsepm\event\synapse\SynapsePluginMessageReceiveEvent;
use synapsepm\network\protocol\spp\BroadcastPacket;
use synapsepm\network\protocol\spp\ConnectPacket;
use synapsepm\network\protocol\spp\DataPacket;
use synapsepm\network\protocol\spp\DisconnectPacket;
use synapsepm\network\protocol\spp\HeartbeatPacket;
use synapsepm\network\protocol\spp\InformationPacket;
use synapsepm\network\protocol\spp\PlayerLoginPacket;
use synapsepm\network\protocol\spp\PlayerLogoutPacket;
use synapsepm\network\protocol\spp\PluginMessagePacket;
use synapsepm\network\protocol\spp\RedirectPacket;
use synapsepm\network\protocol\spp\SynapseInfo;
use synapsepm\network\SynapseInterface;
use synapsepm\network\SynLibInterface;


class Synapse {
    /** @var SynapsePM */
    private $owner;
    /** @var Task */
    private $task;
    /** @var Server */
    private $server;
    /** @var MainLogger */
    private $logger;

    /** @var string */
    private $serverIp;
    /** @var int */
    private $port;

    /** @var string */
    private $description;
    /** @var bool */
    private $isMainServer;

    private $fallbackServer;

    private $transferOnShutdown;

    /** @var string */
    private $password;

    /** @var SynapseInterface */
    private $interface;
    /** @var SynLibInterface */
    private $synLibInterface;
    /** @var Player[] */
    private $players = [];

    private $verified = false;
    private $lastUpdate;
    private $lastRecvInfo;
    private $clientData = [];
    private $connectionTime = PHP_INT_MAX;

    public function __construct(SynapsePM $owner, array $config) {
        $this->owner = $owner;
        $this->server = $owner->getServer();
        $this->task = new class($this) extends Task {

            /**
             * @var Synapse
             */
            private $synapse;

            public function __construct(Synapse $synapse) {
                $this->synapse = $synapse;
            }

            public function onRun(int $currentTick) {
                $this->synapse->tick();
            }

        };
        $this->logger = $this->server->getLogger();

        $this->serverIp = (string)($config['server-ip'] ?? '127.0.0.1');
        $this->port = (int)($config['server-port'] ?? 10305);

        $this->description = (string)$config['description'];
        $this->isMainServer = (bool)($config['isMainServer'] ?? true);
        $this->fallbackServer = (bool)($config['isLobbyServer'] ?? false);
        $this->transferOnShutdown = (bool)($config['transferOnShutdown'] ?? true);
        $this->password = (string)$config['password'];

        $this->interface = new SynapseInterface($this, $this->serverIp, $this->port);
        $this->synLibInterface = new SynLibInterface($this, $this->interface);

        $this->lastUpdate = microtime(true);
        $this->lastRecvInfo = microtime(true);

        $this->owner->getScheduler()->scheduleRepeatingTask($this->task, 1);

        MainLogger::getLogger()->info("password_ " . $this->password);
        if ($config['autoConnect'] ?? false) {
            $this->connect();
        }
    }

    public function getClientData() {
        return $this->clientData;
    }

    public function getServer() {
        return $this->server;
    }

    public function getInterface() {
        return $this->interface;
    }

    public function shutdown() {
        if ($this->verified) {
            $pk = new DisconnectPacket();
            $pk->type = DisconnectPacket::TYPE_GENERIC;
            $pk->message = 'Server closed';
            $this->sendDataPacket($pk);
            $this->getLogger()->debug('Synapse client has disconnected from Synapse server');
        }
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function sendDataPacket(DataPacket $pk) {
        $this->interface->putPacket($pk);
    }

    public function connect() {
        $this->verified = false;
        $pk = new ConnectPacket();
        $pk->password = md5($this->password);
        $pk->isMainServer = $this->isMainServer();
        $pk->isFallbackServer = $this->fallbackServer;
        $pk->transferOnShutdown = $this->transferOnShutdown;
        $pk->description = $this->description;
        $pk->maxPlayers = $this->server->getMaxPlayers();
        $pk->protocol = SynapseInfo::CURRENT_PROTOCOL;
        $this->sendDataPacket($pk);
        $this->connectionTime = microtime(true);
    }

    public function tick() {
        $this->interface->process();
        if ((($time = microtime(true)) - $this->lastUpdate) >= 5) {
            $this->lastUpdate = $time;
            $pk = new HeartbeatPacket();
            $pk->tps = $this->server->getTicksPerSecondAverage();
            $pk->load = $this->server->getTickUsageAverage();
            $pk->upTime = (int)(microtime(true) - \pocketmine\START_TIME);
            $this->sendDataPacket($pk);
        }
        if (((($time = microtime(true)) - $this->lastUpdate) >= 30) and $this->interface->isConnected()) {
            $this->interface->reconnect();
        }
        if (microtime(true) - $this->connectionTime >= 15 and !$this->verified) {
            $this->interface->reconnect();
        }
    }

    public function getServerIp(): string {
        return $this->serverIp;
    }

    public function getPort(): int {
        return $this->port;
    }

    public function isMainServer(): bool {
        return $this->isMainServer;
    }

    public function broadcastPacket(array $players, DataPacket $packet, $direct = false) {
        $packet->encode();
        $pk = new BroadcastPacket();
        $pk->direct = $direct;
        $pk->payload = $packet->getBuffer();
        foreach ($players as $player) {
            $pk->entries[] = $player->getUniqueId();
        }
        $this->sendDataPacket($pk);
    }

    public function getLogger() {
        return $this->logger;
    }

    public function getHash(): string {
        return $this->serverIp . ':' . $this->port;
    }

    public function getHashByDescription(string $desc): ?string {
        foreach ($this->clientData as $hash => $data) {
            if ($data["description"] === $desc) {
                return $hash;
            }
        }

        return null;
    }

    public function getPacket($buffer) {
        $pid = ord($buffer{0});
        if ($pid === 0xFF) {
            $pid = 0xFE;
        }
        if (($data = PacketPool::getPacketById($pid)) === null) {
            return null;
        }
        $data->setBuffer($buffer, 1);
        return $data;
    }

    /**
     * @internal
     * @param Player $player
     */
    public function removePlayer(Player $player) {
        if (isset($this->players[$uuid = $player->getUniqueId()->toBinary()])) {
            unset($this->players[$uuid]);
        }
    }

    public function handleDataPacket(DataPacket $pk) {
        switch ($pk::NETWORK_ID) {
            case SynapseInfo::DISCONNECT_PACKET:
                /** @var DisconnectPacket $pk */
                $this->verified = false;
                switch ($pk->type) {
                    case DisconnectPacket::TYPE_GENERIC:
                        $this->getLogger()->notice('Synapse Client has disconnected due to ' . $pk->message);
                        $this->interface->reconnect();
                        break;
                    case DisconnectPacket::TYPE_WRONG_PROTOCOL:
                        $this->getLogger()->error($pk->message);
                        break;
                }
                break;
            case SynapseInfo::INFORMATION_PACKET:
                /** @var InformationPacket $pk */
                switch ($pk->type) {
                    case InformationPacket::TYPE_LOGIN:
                        if ($pk->message === InformationPacket::INFO_LOGIN_SUCCESS) {
                            $this->logger->info('Login success to ' . $this->serverIp . ':' . $this->port);
                            $this->verified = true;
                        } elseif ($pk->message === InformationPacket::INFO_LOGIN_FAILED) {
                            $this->logger->info('Login failed to ' . $this->serverIp . ':' . $this->port);
                        }
                        break;
                    case InformationPacket::TYPE_CLIENT_DATA:
                        $this->clientData = json_decode($pk->message, true)['clientList'];
                        $this->lastRecvInfo = microtime();
                        break;
                }
                break;
            case SynapseInfo::PLAYER_LOGIN_PACKET:
                /** @var PlayerLoginPacket $pk */
                $ev = new PlayerCreationEvent($this->synLibInterface, Player::class, Player::class, $pk->address, $pk->port);
                $this->server->getPluginManager()->callEvent($ev);
                $class = $ev->getPlayerClass();

                /** @var Player $player */
                $player = new $class($this->synLibInterface, $ev->getAddress(), $ev->getPort());
                $player->setUniqueId($pk->uuid);
                $this->server->addPlayer($player);
                $this->players[$pk->uuid->toBinary()] = $player;
                $player->handleLoginPacket($pk);
                break;
            case SynapseInfo::REDIRECT_PACKET:
                /** @var RedirectPacket $pk */
                if (isset($this->players[$uuid = $pk->uuid->toBinary()])) {
                    $innerPacket = $this->getPacket($pk->mcpeBuffer);
                    if ($innerPacket !== null) {
                        $this->players[$uuid]->handleDataPacket($innerPacket);
                    }
                }
                break;
            case SynapseInfo::PLAYER_LOGOUT_PACKET:
                /** @var PlayerLogoutPacket $pk */
                if (isset($this->players[$uuid = $pk->uuid->toBinary()])) {
                    $this->players[$uuid]->close('', $pk->reason, false);
                    $this->removePlayer($this->players[$uuid]);
                }
                break;
            case SynapseInfo::PLUGIN_MESSAGE_PACKET:
                /** @var PluginMessagePacket $pk */
                $this->server->getPluginManager()->callEvent(new SynapsePluginMessageReceiveEvent($this, $pk->channel, $pk->data));
                break;
        }
    }

    public function sendPluginMessage(string $channel, string $message) {
        $pk = new PluginMessagePacket();
        $pk->channel = $channel;
        $pk->data = $message;
        $this->sendDataPacket($pk);
    }
}
