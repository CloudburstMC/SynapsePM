<?php
declare(strict_types=1);

namespace synapsepm\event\synapse;

use synapsepm\Synapse;


class SynapsePluginMessageReceiveEvent extends SynapseEvent {
    public static $handlerList = null;

    /** @var string */
    protected $message;

    /**
     * @var string
     */
    protected $channel;

    /**
     * SynapsePluginMessageReceiveEvent constructor.
     *
     * @param Synapse $synapse
     * @param string $channel
     * @param string $message
     */
    public function __construct(Synapse $synapse, string $channel, string $message) {
        $this->synapse = $synapse;
        $this->channel = $channel;
        $this->message = $message;
    }

    public function getMessage(): string {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getChannel(): string {
        return $this->channel;
    }
}