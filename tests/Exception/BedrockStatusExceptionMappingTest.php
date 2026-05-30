<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Exception;

use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftBedrockStatus;
use PHPUnit\Framework\TestCase;

final class BedrockStatusExceptionMappingTest extends TestCase
{
    private const OFFLINE_MESSAGE_DATA_ID = "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE\xFD\xFD\xFD\xFD\x12\x34\x56\x78";

    public function testEmptyPayloadThrowsProtocolException(): void
    {
        $client = new BedrockStatusExceptionMappingDouble();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('First byte is not ID_UNCONNECTED_PONG.');

        $client->validatePongForTest('', self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testInvalidFirstByteThrowsProtocolException(): void
    {
        $client = new BedrockStatusExceptionMappingDouble();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('First byte is not ID_UNCONNECTED_PONG.');

        $client->validatePongForTest("\x00", self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testInvalidMagicBytesThrowsProtocolException(): void
    {
        $client = new BedrockStatusExceptionMappingDouble();
        $payload = "\x1C" . str_repeat("\x00", 16) . str_repeat("\x01", 16);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Magic bytes do not match.');

        $client->validatePongForTest($payload, self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testValidPongDoesNotThrowException(): void
    {
        $client = new BedrockStatusExceptionMappingDouble();
        $payload = "\x1C" . str_repeat("\x00", 16) . self::OFFLINE_MESSAGE_DATA_ID;

        $client->validatePongForTest($payload, self::OFFLINE_MESSAGE_DATA_ID);

        self::assertTrue(true);
    }

    public function testProtocolExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new ProtocolException());
    }
}

final class BedrockStatusExceptionMappingDouble extends MinecraftBedrockStatus
{
    public function __construct()
    {
        parent::__construct('example.org', 19132, 3, false);
    }

    public function validatePongForTest(string $data, string $offlineMessageDataId): void
    {
        $this->validatePong($data, $offlineMessageDataId);
    }
}
