<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

final class ProtocolPacketFactory
{
    public const BEDROCK_OFFLINE_MESSAGE_DATA_ID = BedrockRakNetProtocol::OFFLINE_MESSAGE_DATA_ID;

    public static function javaStatusResponse(string $json): string
    {
        return JavaStatusProtocol::statusResponse($json);
    }

    public static function bedrockPong(string $statusPayload): string
    {
        return self::bedrockPongWithMagic(self::BEDROCK_OFFLINE_MESSAGE_DATA_ID, $statusPayload);
    }

    public static function bedrockPongWithMagic(string $magicBytes, string $statusPayload): string
    {
        return BedrockRakNetProtocol::unconnectedPong($statusPayload, $magicBytes);
    }
}
