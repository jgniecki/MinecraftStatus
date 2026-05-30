<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

final class BedrockRakNetProtocol
{
    public const OFFLINE_MESSAGE_DATA_ID = "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE\xFD\xFD\xFD\xFD\x12\x34\x56\x78";

    public static function unconnectedPong(
        string $statusPayload,
        string $magicBytes = self::OFFLINE_MESSAGE_DATA_ID,
        string $serverGuid = "\x00\x00\x00\x00\x00\x00\x00\x02"
    ): string {
        return "\x1C"
            . str_repeat("\x00", 8)
            . $serverGuid
            . $magicBytes
            . pack('n', strlen($statusPayload))
            . $statusPayload;
    }

    /**
     * @return array{packetId: int, pingTime: string, magic: string, clientGuid: string}
     */
    public static function decodeUnconnectedPing(string $request): array
    {
        return [
            'packetId' => isset($request[0]) ? ord($request[0]) : -1,
            'pingTime' => substr($request, 1, 8),
            'magic' => substr($request, 9, 16),
            'clientGuid' => substr($request, 25, 8),
        ];
    }
}
