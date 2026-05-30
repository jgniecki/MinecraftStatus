<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Integration\MockServer;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\MinecraftBedrockStatus;
use DevLancer\MinecraftStatus\Result\BedrockStatusResult;
use DevLancer\MinecraftStatus\StatusState;
use DevLancer\MinecraftStatus\Tests\Support\BedrockRakNetProtocol;
use DevLancer\MinecraftStatus\Tests\Support\LocalSocketServer;
use PHPUnit\Framework\TestCase;

final class BedrockSocketFlowTest extends TestCase
{
    protected function setUp(): void
    {
        if (!LocalSocketServer::isAvailable()) {
            self::markTestSkipped('proc_open() is required for local socket server tests.');
        }
    }

    public function testFetchParsesUdpPong(): void
    {
        $server = LocalSocketServer::udp(
            BedrockRakNetProtocol::unconnectedPong(
                'MCPE;Bedrock Server;671;1.20.80;5;20;server-id;world;Survival;1;19132;19133;'
            )
        );

        try {
            $client = new MinecraftBedrockStatus('127.0.0.1', $server->port(), 0.5, false);

            self::assertSame($client, $client->fetch());
            self::assertSame(StatusState::Fetched, $client->status());

            $result = $client->getResult();

            self::assertInstanceOf(BedrockStatusResult::class, $result);
            self::assertSame('Bedrock Server', $result->motd());
            self::assertSame(5, $result->onlinePlayers());
            self::assertSame(20, $result->maxPlayers());
            self::assertSame(671, $result->protocol);
            self::assertSame('1.20.80', $result->version);
            self::assertSame('server-id', $result->serverId);
            self::assertSame('world', $result->map);
            self::assertSame(19132, $result->ipv4Port);
            self::assertSame(19133, $result->ipv6Port);
            self::assertSame($client->getInfo(), $result->raw());

            $requests = $server->requests(1);

            self::assertCount(1, $requests);

            $ping = BedrockRakNetProtocol::decodeUnconnectedPing($requests[0]);

            self::assertSame(0x01, $ping['packetId']);
            self::assertSame(BedrockRakNetProtocol::OFFLINE_MESSAGE_DATA_ID, $ping['magic']);
            self::assertSame(8, strlen($ping['clientGuid']));
        } finally {
            $server->stop();
        }
    }

    public function testSemicolonInBedrockHostnameFailsFetch(): void
    {
        $server = LocalSocketServer::udp(
            BedrockRakNetProtocol::unconnectedPong(
                'MCPE;Name;With;Semicolon;671;1.20.80;5;20;server-id;world;Survival;1;19132;19133;'
            )
        );

        try {
            $client = new MinecraftBedrockStatus('127.0.0.1', $server->port(), 0.5, false);

            try {
                $client->fetch();
                self::fail('Expected Bedrock hostname containing semicolons to fail the fetch.');
            } catch (InvalidResponseException $exception) {
                self::assertSame('Failed to parse server\'s response.', $exception->getMessage());
                self::assertSame(StatusState::Failed, $client->status());
            }
        } finally {
            $server->stop();
        }
    }

    public function testInvalidMagicBytesFailFetchAndClearPreviousResult(): void
    {
        $server = LocalSocketServer::udpSequence([
            BedrockRakNetProtocol::unconnectedPong(
                'MCPE;Bedrock Server;671;1.20.80;5;20;server-id;world;Survival;1;19132;19133;'
            ),
            BedrockRakNetProtocol::unconnectedPong(
                'MCPE;Broken Server;671;1.20.80;5;20;server-id;world;Survival;1;19132;19133;',
                str_repeat("\x01", 16),
            ),
        ]);

        try {
            $client = new MinecraftBedrockStatus('127.0.0.1', $server->port(), 0.5, false);
            $client->fetch();

            self::assertSame(StatusState::Fetched, $client->status());
            self::assertSame('Bedrock Server', $client->getResult()->motd());

            try {
                $client->fetch();
                self::fail('Expected invalid Bedrock magic bytes to fail the fetch.');
            } catch (ProtocolException $exception) {
                self::assertSame('Magic bytes do not match.', $exception->getMessage());
                self::assertSame(StatusState::Failed, $client->status());
            }

            $this->expectException(NotConnectedException::class);

            $client->getResult();
        } finally {
            $server->stop();
        }
    }
}
