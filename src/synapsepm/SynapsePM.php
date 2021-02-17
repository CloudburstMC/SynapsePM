<?php
declare(strict_types=1);

namespace synapsepm;

use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\plugin\PluginBase;
use synapsepm\command\TransferCommand;
use synapsepm\utils\Utils;


class SynapsePM extends PluginBase {

    /**
     * @var SynapsePM
     */
    private static $instance;

    /** @var Synapse[] */
    private $synapses = [];
    /** @var bool */
    private $useLoadingScreen;

    public function onLoad(): void {
        @RuntimeBlockMapping::fromStaticRuntimeId(0); //init the mappings

        Utils::initBlockRuntimeIdMapping();
    }

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $cfg = $this->getConfig();

        if (!$cfg->get('enable')) {
            $this->setEnabled(false);
            return;
        }

        if ($cfg->get('disable-rak')) {
            $this->getServer()->getPluginManager()->registerEvents(new DisableRakListener(), $this);
        }

        foreach ($this->getConfig()->get('entries') as $synapseConfig) {
//            if ($synapseConfig['enabled']) {
            $this->addSynapse(new Synapse($this, $synapseConfig));
//            }
        }

//        $this->useLoadingScreen = (bool)$this->getConfig()->get('loadingScreen', true);

        $this->getServer()->getCommandMap()->register("stransfer", new TransferCommand());
    }

    public function onDisable(): void {
        foreach ($this->synapses as $synapse) {
            $synapse->shutdown();
        }
    }

    /**
     * Add the synapse to the synapses list
     *
     * @param Synapse $synapse
     */
    public function addSynapse(Synapse $synapse): void {
        $this->synapses[spl_object_hash($synapse)] = $synapse;
    }

    /**
     * Remove the synapse from the synapses list
     *
     * @param Synapse $synapse
     */
    public function removeSynapse(Synapse $synapse): void {
        unset($this->synapses[spl_object_hash($synapse)]);
    }

    /**
     * Return array of the synapses
     * @return Synapse[]
     */
    public function getSynapses(): array {
        return $this->synapses;
    }

    /**
     * @return boolean
     */
    public function isUseLoadingScreen(): bool {
        return $this->useLoadingScreen;
    }

    public static function getInstance(): SynapsePM {
        return self::$instance;
    }
}