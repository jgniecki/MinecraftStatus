<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Integration\MockServer;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;
use DevLancer\MinecraftStatus\Result\JavaStatusResult;
use DevLancer\MinecraftStatus\StatusState;
use DevLancer\MinecraftStatus\Tests\Support\JavaStatusProtocol;
use DevLancer\MinecraftStatus\Tests\Support\LocalSocketServer;
use PHPUnit\Framework\TestCase;

final class JavaStatusSocketFlowTest extends TestCase
{
    protected function setUp(): void
    {
        if (!LocalSocketServer::isAvailable()) {
            self::markTestSkipped('proc_open() is required for local socket server tests.');
        }
    }

    public function testFetchParsesTcpStatusResponse(): void
    {
        $server = LocalSocketServer::tcp($this->statusResponse('Socket MOTD'));

        try {
            $client = new MinecraftJavaStatus('127.0.0.1', $server->port(), 0.5, false);

            self::assertSame($client, $client->fetch());
            self::assertSame(StatusState::Fetched, $client->status());

            $result = $client->getResult();

            self::assertInstanceOf(JavaStatusResult::class, $result);
            self::assertSame('Socket MOTD', $result->motd());
            self::assertSame(2, $result->onlinePlayers());
            self::assertSame(20, $result->maxPlayers());
            self::assertSame(765, $result->protocol);
            self::assertSame('1.20.4', $result->versionName);
            self::assertSame('data:image/png;base64,test', $result->favicon);
            self::assertSame([
                ['name' => 'Steve', 'id' => '00000000-0000-0000-0000-000000000001'],
                ['name' => 'Alex', 'id' => '00000000-0000-0000-0000-000000000002'],
            ], $result->players);

            $requests = $server->requests(1);

            self::assertCount(1, $requests);
            $packets = JavaStatusProtocol::decodeClientPackets($requests[0]);

            self::assertCount(2, $packets);

            $handshake = JavaStatusProtocol::decodeHandshakePacket($packets[0]);

            self::assertSame(0, $handshake['packetId']);
            self::assertSame('127.0.0.1', $handshake['serverAddress']);
            self::assertSame($server->port(), $handshake['serverPort']);
            self::assertSame(1, $handshake['nextState']);
            self::assertSame(0, JavaStatusProtocol::decodePacketId($packets[1]));
        } finally {
            $server->stop();
        }
    }

    public function testUnexpectedStatusPacketIdThrowsProtocolException(): void
    {
        $server = LocalSocketServer::tcp($this->statusResponse('Socket MOTD', 1));

        try {
            $client = new MinecraftJavaStatus('127.0.0.1', $server->port(), 0.5, false);

            $this->expectException(ProtocolException::class);
            $this->expectExceptionMessage('Failed to receive status.');

            $client->fetch();
        } finally {
            $server->stop();
        }
    }

    public function testHandshakeUsesVarIntLengthsForLongServerAddress(): void
    {
        $server = LocalSocketServer::tcp($this->statusResponse('Socket MOTD'));
        $longHost = str_repeat('a', 130) . '.example.com';

        try {
            $client = new JavaStatusLongHostSocketDouble($longHost, $server->port(), 0.5, false);
            $client->fetch();

            $requests = $server->requests(1);
            self::assertCount(1, $requests);

            $packets = JavaStatusProtocol::decodeClientPackets($requests[0]);
            self::assertCount(2, $packets);

            $handshake = JavaStatusProtocol::decodeHandshakePacket($packets[0]);
            self::assertSame($longHost, $handshake['serverAddress']);
            self::assertSame($server->port(), $handshake['serverPort']);
            self::assertSame(1, $handshake['nextState']);
        } finally {
            $server->stop();
        }
    }


    public function testInvalidJsonFromTcpResponseFailsFetchAndClearsPreviousResult(): void
    {
        $server = LocalSocketServer::tcpSequence([
            $this->statusResponse('Socket MOTD'),
            JavaStatusProtocol::statusResponse('{invalid'),
        ]);

        try {
            $client = new MinecraftJavaStatus('127.0.0.1', $server->port(), 0.5, false);
            $client->fetch();

            self::assertSame(StatusState::Fetched, $client->status());
            self::assertSame('Socket MOTD', $client->getResult()->motd());

            try {
                $client->fetch();
                self::fail('Expected invalid JSON from TCP response to fail the fetch.');
            } catch (InvalidResponseException $exception) {
                self::assertStringStartsWith('JSON parsing failed:', $exception->getMessage());
                self::assertSame(StatusState::Failed, $client->status());
            }

            $this->expectException(NotConnectedException::class);

            $client->getResult();
        } finally {
            $server->stop();
        }
    }

    private function statusResponse(string $motd, int $packetId = 0): string
    {
        $json = json_encode([
            'description' => $motd,
            'players' => [
                'online' => 2,
                'max' => 20,
                'sample' => [
                    ['name' => 'Steve', 'id' => '00000000-0000-0000-0000-000000000001'],
                    ['name' => 'Alex', 'id' => '00000000-0000-0000-0000-000000000002'],
                ],
            ],
            'version' => [
                'name' => '1.20.4',
                'protocol' => 765,
            ],
            'favicon' => 'data:image/png;base64,test',
        ], JSON_THROW_ON_ERROR);

        return JavaStatusProtocol::statusResponse($json, $packetId);
    }
}

final class JavaStatusLongHostSocketDouble extends MinecraftJavaStatus
{
    protected function _connect(string $host, int $port): void
    {
        parent::_connect('127.0.0.1', $port);
    }
}
