<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

final class JavaStatusProtocol
{
    public static function statusResponse(string $json, int $packetId = 0): string
    {
        $packet = self::varInt($packetId) . self::varInt(strlen($json)) . $json;

        return self::varInt(strlen($packet)) . $packet;
    }

    public static function varInt(int $value): string
    {
        $result = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value !== 0) {
                $byte |= 0x80;
            }

            $result .= chr($byte);
        } while ($value !== 0);

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function decodeClientPackets(string $request): array
    {
        $offset = 0;
        $packets = [];
        $length = strlen($request);

        while ($offset < $length) {
            $packetLength = self::readVarInt($request, $offset);
            $packets[] = substr($request, $offset, $packetLength);
            $offset += $packetLength;
        }

        return $packets;
    }

    /**
     * @return array{packetId: int, protocolVersion: int, serverAddress: string, serverPort: int, nextState: int}
     */
    public static function decodeHandshakePacket(string $packet): array
    {
        $offset = 0;
        $packetId = self::readVarInt($packet, $offset);
        $protocolVersion = self::readVarInt($packet, $offset);
        $addressLength = self::readVarInt($packet, $offset);
        $serverAddress = substr($packet, $offset, $addressLength);
        $offset += $addressLength;

        $port = unpack('n', substr($packet, $offset, 2));
        $offset += 2;

        return [
            'packetId' => $packetId,
            'protocolVersion' => $protocolVersion,
            'serverAddress' => $serverAddress,
            'serverPort' => is_array($port) ? (int)$port[1] : 0,
            'nextState' => self::readVarInt($packet, $offset),
        ];
    }

    public static function decodePacketId(string $packet): int
    {
        $offset = 0;

        return self::readVarInt($packet, $offset);
    }

    private static function readVarInt(string $data, int &$offset): int
    {
        $result = 0;
        $shift = 0;

        do {
            if (!isset($data[$offset])) {
                break;
            }

            $byte = ord($data[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) === 0x80);

        return $result;
    }
}
