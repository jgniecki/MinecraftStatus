<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Integration\MockServer;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use DevLancer\MinecraftStatus\Result\JavaQueryResult;
use DevLancer\MinecraftStatus\StatusState;
use DevLancer\MinecraftStatus\Tests\Support\JavaQueryProtocol;
use DevLancer\MinecraftStatus\Tests\Support\LocalSocketServer;
use PHPUnit\Framework\TestCase;

final class JavaQuerySocketFlowTest extends TestCase
{
    protected function setUp(): void
    {
        if (!LocalSocketServer::isAvailable()) {
            self::markTestSkipped('proc_open() is required for local socket server tests.');
        }
    }

    public function testFetchParsesUdpQueryResponseAndSendsGameSpyRequests(): void
    {
        $server = LocalSocketServer::udpSequence([
            JavaQueryProtocol::challengeResponse(12345),
            JavaQueryProtocol::fullStatResponse([
                'hostname' => 'Query Server',
                'numplayers' => '2',
                'maxplayers' => '20',
                'map' => 'world',
            ], ['Steve', 'Alex']),
        ]);

        try {
            $client = new MinecraftJavaQuery('127.0.0.1', $server->port(), 0.5, false);

            self::assertSame($client, $client->fetch());
            self::assertSame(StatusState::Fetched, $client->status());

            $result = $client->getResult();

            self::assertInstanceOf(JavaQueryResult::class, $result);
            self::assertSame('Query Server', $result->motd());
            self::assertSame(2, $result->onlinePlayers());
            self::assertSame(20, $result->maxPlayers());
            self::assertSame('127.0.0.1', $result->hostIp);
            self::assertSame(['Steve', 'Alex'], $result->players);

            $requests = $server->requests(2);

            self::assertCount(2, $requests);

            $challengeRequest = JavaQueryProtocol::decodeRequestHeader($requests[0]);
            self::assertSame("\xFE\xFD", $challengeRequest['magic']);
            self::assertSame(0x09, $challengeRequest['type']);
            self::assertSame(JavaQueryProtocol::SESSION_ID, $challengeRequest['sessionId']);

            $fullStatRequest = JavaQueryProtocol::decodeFullStatRequest($requests[1]);
            self::assertSame("\xFE\xFD", $fullStatRequest['magic']);
            self::assertSame(0x00, $fullStatRequest['type']);
            self::assertSame(JavaQueryProtocol::SESSION_ID, $fullStatRequest['sessionId']);
            self::assertSame(12345, $fullStatRequest['challengeToken']);
            self::assertSame("\x00\x00\x00\x00", $fullStatRequest['padding']);
        } finally {
            $server->stop();
        }
    }

    public function testMissingChallengeThrowsProtocolException(): void
    {
        $server = LocalSocketServer::udp("\x09");

        try {
            $client = new MinecraftJavaQuery('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(ProtocolException::class);
            $this->expectExceptionMessage('Failed to receive challenge.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }

    public function testChallengeWithWrongSessionIdThrowsProtocolException(): void
    {
        $server = LocalSocketServer::udp(
            JavaQueryProtocol::challengeResponse(12345, "\x05\x06\x07\x08")
        );

        try {
            $client = new MinecraftJavaQuery('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(ProtocolException::class);
            $this->expectExceptionMessage('Failed to receive challenge.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }

    public function testStatusWithWrongSessionIdThrowsProtocolException(): void
    {
        $server = LocalSocketServer::udpSequence([
            JavaQueryProtocol::challengeResponse(12345),
            JavaQueryProtocol::fullStatResponse([
                'hostname' => 'Query Server',
                'numplayers' => '2',
                'maxplayers' => '20',
            ], ['Steve'], "\x05\x06\x07\x08"),
        ]);

        try {
            $client = new MinecraftJavaQuery('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(ProtocolException::class);
            $this->expectExceptionMessage('Failed to receive status.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }

    public function testInvalidPlayerSectionThrowsInvalidResponseException(): void
    {
        $server = LocalSocketServer::udpSequence([
            JavaQueryProtocol::challengeResponse(12345),
            "\x00" . JavaQueryProtocol::SESSION_ID . "splitnum\x00\x80\x00hostname\x00Query Server\x00",
        ]);

        try {
            $client = new MinecraftJavaQuery('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(InvalidResponseException::class);
            $this->expectExceptionMessage('Failed to parse server\'s response.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }
}
