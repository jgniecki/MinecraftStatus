<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Result;

use DevLancer\MinecraftStatus\AbstractStatus;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftBedrockStatus;
use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;
use DevLancer\MinecraftStatus\Result\BedrockStatusResult;
use DevLancer\MinecraftStatus\Result\GenericStatusResult;
use DevLancer\MinecraftStatus\Result\JavaQueryResult;
use DevLancer\MinecraftStatus\Result\JavaStatusResult;
use DevLancer\MinecraftStatus\Result\LegacyJavaStatusResult;
use PHPUnit\Framework\TestCase;

final class ClientResultTest extends TestCase
{
    public function testResultBeforeFetchThrowsNotConnectedException(): void
    {
        $client = new GenericResultDouble();

        $this->expectException(NotConnectedException::class);

        $client->getResult();
    }

    public function testGenericResultRemainsAvailableAfterDisconnect(): void
    {
        $client = new GenericResultDouble();

        $client->fetch();
        $client->disconnect();

        $result = $client->getResult();

        self::assertInstanceOf(GenericStatusResult::class, $result);
        self::assertSame($client->getInfo(), $result->raw());
        self::assertSame('generic motd', $result->motd());
        self::assertSame(3, $result->onlinePlayers());
        self::assertSame(9, $result->maxPlayers());
    }

    public function testFailedRefreshClearsOldResult(): void
    {
        $client = new GenericResultDouble();

        $client->fetch();
        $client->failNextFetch = true;

        try {
            $client->fetch();
            self::fail('Expected fetch to fail.');
        } catch (ReceiveStatusException) {
        }

        $this->expectException(NotConnectedException::class);

        $client->getResult();
    }

    public function testJavaStatusReturnsTypedResult(): void
    {
        $client = new JavaStatusResultDouble();

        $client->fetch();

        $result = $client->getResult();

        self::assertInstanceOf(JavaStatusResult::class, $result);
        self::assertSame($client->getInfo(), $result->raw());
        self::assertSame('{"text":"Java MOTD"}', $result->motd());
        self::assertSame(2, $result->onlinePlayers());
        self::assertSame(10, $result->maxPlayers());
        self::assertSame(765, $result->protocol);
        self::assertSame('1.20.4', $result->versionName);
        self::assertSame('data:image/png;base64,abc', $result->favicon);
        self::assertSame(42, $result->delay);
        self::assertSame(['Steve', 'Alex'], $result->players);
    }

    public function testJavaQueryReturnsTypedResult(): void
    {
        $client = new JavaQueryResultDouble();

        $client->fetch();

        $result = $client->getResult();

        self::assertInstanceOf(JavaQueryResult::class, $result);
        self::assertSame($client->getInfo(), $result->raw());
        self::assertSame('Query MOTD', $result->motd());
        self::assertSame(4, $result->onlinePlayers());
        self::assertSame(12, $result->maxPlayers());
        self::assertSame('127.0.0.1', $result->hostIp);
        self::assertSame(['Steve'], $result->players);
    }

    public function testBedrockReturnsTypedResult(): void
    {
        $client = new BedrockResultDouble();

        $client->fetch();

        $result = $client->getResult();

        self::assertInstanceOf(BedrockStatusResult::class, $result);
        self::assertSame($client->getInfo(), $result->raw());
        self::assertSame('Bedrock MOTD', $result->motd());
        self::assertSame(5, $result->onlinePlayers());
        self::assertSame(20, $result->maxPlayers());
        self::assertSame(671, $result->protocol);
        self::assertSame('1.20.80', $result->version);
        self::assertSame('Survival', $result->gameMode);
        self::assertSame('world', $result->map);
        self::assertSame('server-id', $result->serverId);
        self::assertSame(19132, $result->ipv4Port);
        self::assertSame(19133, $result->ipv6Port);
    }

    public function testLegacyJavaReturnsTypedResult(): void
    {
        $client = new LegacyJavaResultDouble();

        $client->fetch();

        $result = $client->getResult();

        self::assertInstanceOf(LegacyJavaStatusResult::class, $result);
        self::assertSame($client->getInfo(), $result->raw());
        self::assertSame('Legacy MOTD', $result->motd());
        self::assertSame(6, $result->onlinePlayers());
        self::assertSame(30, $result->maxPlayers());
        self::assertSame(47, $result->protocol);
        self::assertSame('1.8', $result->versionName);
    }
}

final class GenericResultDouble extends AbstractStatus
{
    public bool $failNextFetch = false;

    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    public function getCountPlayers(): int
    {
        return 0;
    }

    public function getMaxPlayers(): int
    {
        return 0;
    }

    public function getMotd(): string
    {
        return '';
    }

    protected function _connect(string $host, int $port): void
    {
        $this->socket = self::createSocket();
    }

    protected function getStatus(): void
    {
        if ($this->failNextFetch) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->info = [
            'motd' => 'generic motd',
            'players' => ['online' => 3, 'max' => 9],
        ];
    }

    /**
     * @return resource
     */
    public static function createSocket()
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        return $socket;
    }
}

final class JavaStatusResultDouble extends MinecraftJavaStatus
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $this->socket = GenericResultDouble::createSocket();
    }

    protected function getStatus(): void
    {
        $this->info = [
            'description' => ['text' => 'Java MOTD'],
            'players' => ['online' => 2, 'max' => 10],
            'version' => ['protocol' => 765, 'name' => '1.20.4'],
            'favicon' => 'data:image/png;base64,abc',
        ];
        $this->players = ['Steve', 'Alex'];
        $this->delay = 42;
    }
}

final class JavaQueryResultDouble extends MinecraftJavaQuery
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $this->socket = GenericResultDouble::createSocket();
    }

    protected function getStatus(): void
    {
        $this->info = [
            'hostname' => 'Query MOTD',
            'numplayers' => '4',
            'maxplayers' => '12',
            'hostip' => '127.0.0.1',
        ];
        $this->players = ['Steve'];
    }
}

final class BedrockResultDouble extends MinecraftBedrockStatus
{
    public function __construct()
    {
        parent::__construct('example.org', 19132, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $this->socket = GenericResultDouble::createSocket();
    }

    protected function getStatus(): void
    {
        $this->info = [
            'hostname' => 'Bedrock MOTD',
            'numplayers' => 5,
            'maxplayers' => 20,
            'protocol' => 671,
            'version' => '1.20.80',
            'game_mode' => 'Survival',
            'map' => 'world',
            'server_id' => 'server-id',
            'ipv4port' => 19132,
            'ipv6port' => 19133,
        ];
    }
}

final class LegacyJavaResultDouble extends MinecraftJavaPreOld17Status
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $this->socket = GenericResultDouble::createSocket();
    }

    protected function getStatus(): void
    {
        $this->info = [
            'description' => ['text' => 'Legacy MOTD'],
            'players' => ['online' => 6, 'max' => 30],
            'version' => ['protocol' => 47, 'name' => '1.8'],
        ];
    }
}
