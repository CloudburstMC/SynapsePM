<?php

declare(strict_types=1);

namespace synapsepm\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use synapsepm\Player as SynapsePlayer;

/**
 * @author CreeperFace
 */
class TransferCommand extends Command {

    public function __construct(){
        parent::__construct("stransfer");
        $this->setPermission("synapse.cmd.transfer");
        $this->setUsage("/stransfer <server> [target player]");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(!$this->testPermission($sender)){
            return;
        }

        if(count($args) < 1 || count($args) > 2 || (!$sender instanceof Player && count($args) < 2)){
            $sender->sendMessage($this->getUsage());
            return;
        }

        $server = $args[0];
        $target = $args[1] ?? $sender;

        if(!$target instanceof Player){
            $target = Server::getInstance()->getPlayer((string)$target);
        }

        if(!$target instanceof Player){
            $sender->sendMessage(TextFormat::RED."Player $target doesn't exist");
            return;
        }

        if(!$target instanceof SynapsePlayer){
            $sender->sendMessage(TextFormat::RED."Player {$target->getName()} isn't connected to a synapse server");
            return;
        }

        $target->synapseTransferByDesc($server);
    }
}