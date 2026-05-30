<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Exception;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;
use PHPUnit\Framework\TestCase;

final class JavaStatusExceptionMappingTest extends TestCase
{
    public function testReadVarIntTooBigThrowsProtocolException(): void
    {
        $client = new JavaStatusExceptionMappingDouble();
        $client->setSocketPayload(str_repeat("\x80", 6));

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt too big');

        $client->readVarIntForTest();
    }

    public function testProtocolExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new ProtocolException());
    }

    public function testTimeoutExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new TimeoutException());
    }

    public function testInvalidResponseExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new InvalidResponseException());
    }
}

final class JavaStatusExceptionMappingDouble extends MinecraftJavaStatus
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    public function setSocketPayload(string $payload): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new \RuntimeException('Failed to open test socket.');
        }

        fwrite($socket, $payload);
        rewind($socket);

        $this->socket = $socket;
    }

    public function readVarIntForTest(): int
    {
        return $this->readVarInt();
    }
}
