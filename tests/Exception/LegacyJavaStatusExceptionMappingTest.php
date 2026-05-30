<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Exception;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;
use PHPUnit\Framework\TestCase;

final class LegacyJavaStatusExceptionMappingTest extends TestCase
{
    public function testEmptyPayloadThrowsProtocolException(): void
    {
        $client = new LegacyJavaStatusExceptionMappingDouble();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $client->validateLegacyPacketForTest('');
    }

    public function testShortPayloadThrowsProtocolException(): void
    {
        $client = new LegacyJavaStatusExceptionMappingDouble();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $client->validateLegacyPacketForTest("\xFF\x00\x01");
    }

    public function testPayloadWithoutLegacyPacketByteThrowsProtocolException(): void
    {
        $client = new LegacyJavaStatusExceptionMappingDouble();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $client->validateLegacyPacketForTest("\x00\x00\x00\x00");
    }

    public function testValidLegacyPacketHeaderDoesNotThrowException(): void
    {
        $client = new LegacyJavaStatusExceptionMappingDouble();

        $client->validateLegacyPacketForTest("\xFF\x00\x01\x00");

        self::assertTrue(true);
    }

    public function testInvalidUtf16PayloadThrowsInvalidResponseException(): void
    {
        $client = new LegacyJavaStatusExceptionMappingDouble();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $client->decodeLegacyPayloadForTest("\x00");
    }

    public function testProtocolExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new ProtocolException());
    }

    public function testInvalidResponseExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new InvalidResponseException());
    }
}

final class LegacyJavaStatusExceptionMappingDouble extends MinecraftJavaPreOld17Status
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    public function validateLegacyPacketForTest(string $data): void
    {
        $this->validateLegacyPacket($data);
    }

    public function decodeLegacyPayloadForTest(string $data): string
    {
        return $this->decodeLegacyPayload($data);
    }
}
