<?php
/**
 * @author CreeperFace
 */

declare(strict_types=1);

namespace synapsepm\utils;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

final class DataPacketEidReplacer {

    const REPLACE_METADATA = [Entity::DATA_OWNER_EID, Entity::DATA_LEAD_HOLDER_EID, Entity::DATA_TRADING_PLAYER_EID, Entity::DATA_TARGET_EID];

    public static function replace(DataPacket $pk, int $from, int $to): DataPacket {
        $packet = clone $pk;

        $change = true;

        switch ($packet->pid()) {
            case ProtocolInfo::ADD_PLAYER_PACKET:
            case ProtocolInfo::ADD_ENTITY_PACKET:
            case ProtocolInfo::ADD_ITEM_ENTITY_PACKET:
            case ProtocolInfo::LEVEL_SOUND_EVENT_PACKET_V2:
                self::replaceMetadata($packet->metadata, $from, $to);
                break;
            case ProtocolInfo::ANIMATE_PACKET:
            case ProtocolInfo::SET_ENTITY_MOTION_PACKET:
            case ProtocolInfo::UPDATE_ATTRIBUTES_PACKET:
            case ProtocolInfo::ENTITY_EVENT_PACKET:
            case ProtocolInfo::MOB_EQUIPMENT_PACKET:
            case ProtocolInfo::MOB_EFFECT_PACKET:
            case ProtocolInfo::MOVE_ENTITY_ABSOLUTE_PACKET:
            case ProtocolInfo::MOB_ARMOR_EQUIPMENT_PACKET:
                if ($packet->entityRuntimeId === $from) {
                    $packet->entityRuntimeId = $to;
                }
                break;
            case ProtocolInfo::TAKE_ITEM_ENTITY_PACKET:
                if ($packet->eid === $from) {
                    $packet->eid = $to;
                }
                break;
            case ProtocolInfo::SET_ENTITY_LINK_PACKET:
                $link = $packet->link;

                if ($link->fromEntityUniqueId === $from) {
                    $link->fromEntityUniqueId = $to;
                }

                if ($link->toEntityUniqueId === $from) {
                    $link->toEntityUniqueId = $to;
                }
                break;
            case ProtocolInfo::SET_ENTITY_DATA_PACKET:
                if ($packet->entityRuntimeId === $from) {
                    $packet->entityRuntimeId = $to;
                }

                self::replaceMetadata($packet->metadata, $from, $to);
                break;
            case ProtocolInfo::MOVE_PLAYER_PACKET:
                if ($packet->entityRuntimeId === $from) {
                    $packet->entityRuntimeId = $to;
                }

                if ($packet->ridingEid === $from) {
                    $packet->ridingEid = $to;
                }
                break;
            case ProtocolInfo::PLAYER_LIST_PACKET:
                foreach ($packet->entries as $entry) {
                    if ($entry->entityUniqueId === $from) {
                        $entry->entityUniqueId = $to;
                    }
                }
                break;
            case ProtocolInfo::BOSS_EVENT_PACKET:
                if ($packet->bossEid === $from) {
                    $packet->bossEid = $to;
                }
                break;
            case ProtocolInfo::ADVENTURE_SETTINGS_PACKET:
            case ProtocolInfo::UPDATE_EQUIP_PACKET:
                if ($packet->entityUniqueId === $from) {
                    $packet->entityUniqueId = $to;
                }
                break;
            default:
                $change = false;
                break;
        }

        if ($change && $packet->isEncoded) {
            $packet->encode();
        }

        return $packet;
    }

    private static function replaceMetadata(array &$metadata, int $from, int $to) {
        foreach ($metadata as $key => $d) {
            if ($d[0] === Entity::DATA_TYPE_LONG && $d[1] === $from && in_array($key, self::REPLACE_METADATA)) {
                $d[1] = $to;
            }
        }
    }
}
