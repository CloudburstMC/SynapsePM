<?php
declare(strict_types=1);

namespace synapsepm\utils;

use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\utils\MainLogger;

class Utils {

    const NUKKIT_RUNTIMEID_TABLE = "https://raw.githubusercontent.com/NukkitX/Nukkit/master/src/main/resources/runtime_block_states.dat";

    public static function initBlockRuntimeIdMapping() {
        try {
            $reflect = new \ReflectionClass(RuntimeBlockMapping::class);
            $legacyToRuntimeMap = $reflect->getProperty("legacyToRuntimeMap");
            $runtimeToLegacyMap = $reflect->getProperty("runtimeToLegacyMap");
            $bedrockKnownStates = $reflect->getProperty("bedrockKnownStates");

            $legacyToRuntimeMap->setAccessible(true);
            $runtimeToLegacyMap->setAccessible(true);
            $bedrockKnownStates->setAccessible(true);

            $registerMapping = $reflect->getMethod("registerMapping");
            $registerMapping->setAccessible(true);

            $blockPalette = file_get_contents(self::NUKKIT_RUNTIMEID_TABLE, false, stream_context_create(
                [
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ]
                ]
            ));

            $tag = (new LittleEndianNBTStream())->read($blockPalette);
            if (!($tag instanceof ListTag) or $tag->getTagType() !== NBT::TAG_Compound) { //this is a little redundant currently, but good for auto complete and makes phpstan happy
                throw new \RuntimeException("Invalid blockstates table, expected TAG_List<TAG_Compound> root");
            }

            $bedrockKnownStates->setValue($tag->getValue());
            $runtimeToLegacyMap->setValue([]);
            $legacyToRuntimeMap->setValue([]);

            $setup = $reflect->getMethod("setupLegacyMappings");
            $setup->setAccessible(true);
            $setup->invoke(null);

        } catch (\ReflectionException $e) {
            MainLogger::getLogger()->logException($e);
        }
    }
}
