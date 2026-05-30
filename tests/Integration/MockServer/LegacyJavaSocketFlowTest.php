<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Integration\MockServer;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;
use DevLancer\MinecraftStatus\Result\LegacyJavaStatusResult;
use DevLancer\MinecraftStatus\StatusState;
use DevLancer\MinecraftStatus\Tests\Support\LegacyJavaProtocol;
use DevLancer\MinecraftStatus\Tests\Support\LocalSocketServer;
use PHPUnit\Framework\TestCase;

final class LegacyJavaSocketFlowTest extends TestCase
{
    protected function setUp(): void
    {
        if (!LocalSocketServer::isAvailable()) {
            self::markTestSkipped('proc_open() is required for local socket server tests.');
        }
    }

    public function testFetchParsesModernLegacyResponseAndSendsLegacyRequest(): void
    {
        $server = LocalSocketServer::tcp(
            LegacyJavaProtocol::response("\u{00A7}1\x0047\x001.8.9\x00Legacy MOTD\x005\x0020")
        );

        try {
            $client = new MinecraftJavaPreOld17Status('127.0.0.1', $server->port(), 0.5, false);

            self::assertSame($client, $client->fetch());
            self::assertSame(StatusState::Fetched, $client->status());

            $result = $client->getResult();

            self::assertInstanceOf(LegacyJavaStatusResult::class, $result);
            self::assertSame('Legacy MOTD', $result->motd());
            self::assertSame(5, $result->onlinePlayers());
            self::assertSame(20, $result->maxPlayers());
            self::assertSame(47, $result->protocol);
            self::assertSame('1.8.9', $result->versionName);

            self::assertSame(["\xFE\x01"], $server->requests(1));
        } finally {
            $server->stop();
        }
    }

    public function testFetchParsesOlderLegacySectionSeparatedResponse(): void
    {
        $server = LocalSocketServer::tcp(
            LegacyJavaProtocol::response("Old MOTD\u{00A7}3\u{00A7}10")
        );

        try {
            $client = new MinecraftJavaPreOld17Status('127.0.0.1', $server->port(), 0.5, false);

            $client->fetch();
            $result = $client->getResult();

            self::assertSame('Old MOTD', $result->motd());
            self::assertSame(3, $result->onlinePlayers());
            self::assertSame(10, $result->maxPlayers());
        } finally {
            $server->stop();
        }
    }

    public function testInvalidUtf16ResponseThrowsInvalidResponseException(): void
    {
        $server = LocalSocketServer::tcp("\xFF\x00\x01\x00");

        try {
            $client = new MinecraftJavaPreOld17Status('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(InvalidResponseException::class);
            $this->expectExceptionMessage('Failed to receive status.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }
}
