<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests;

use DevLancer\MinecraftStatus\AbstractStatus;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftBedrockStatus;
use DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use DevLancer\MinecraftStatus\MinecraftJavaStatus;
use DevLancer\MinecraftStatus\StatusState;
use PHPUnit\Framework\TestCase;

final class ClientStateTest extends TestCase
{
    public function testNewClientStartsIdle(): void
    {
        $client = new LifecycleStatusDouble();

        self::assertSame(StatusState::Idle, $client->status());
    }

    public function testFetchReturnsSameInstanceAndMarksClientFetched(): void
    {
        $client = new LifecycleStatusDouble();

        self::assertSame($client, $client->fetch());
        self::assertSame(StatusState::Fetched, $client->status());
        self::assertSame(['motd' => 'ok'], $client->getInfo());
    }

    public function testConnectIsAliasForFetch(): void
    {
        $client = new LifecycleStatusDouble();

        self::assertSame($client, $client->connect());
        self::assertSame(StatusState::Fetched, $client->status());
        self::assertSame(1, $client->connectCount);
    }

    public function testJavaStatusConnectAllowsChainingSpecificMethods(): void
    {
        $client = new JavaStatusDouble();

        self::assertSame('test-icon', $client->connect()->getFavicon());
    }

    public function testJavaQueryConnectAllowsChainingSpecificMethods(): void
    {
        $client = new JavaQueryDouble();

        self::assertSame(['Steve'], $client->connect()->getPlayers());
    }

    public function testBedrockConnectAllowsChainingSpecificMethods(): void
    {
        $client = new BedrockStatusDouble();

        self::assertSame(671, $client->connect()->getProtocol());
    }

    public function testLegacyJavaConnectAllowsChainingSpecificMethods(): void
    {
        $client = new LegacyJavaStatusDouble();

        self::assertSame(47, $client->connect()->getProtocol());
    }

    public function testInfoBeforeFetchThrowsNotConnectedException(): void
    {
        $client = new LifecycleStatusDouble();

        $this->expectException(NotConnectedException::class);

        $client->getInfo();
    }

    public function testInfoRemainsAvailableAfterDisconnect(): void
    {
        $client = new LifecycleStatusDouble();

        $client->fetch();
        $client->disconnect();

        self::assertFalse($client->isConnected());
        self::assertSame(StatusState::Fetched, $client->status());
        self::assertSame(['motd' => 'ok'], $client->getInfo());
    }

    public function testFailedRefreshClearsOldInfoAndClosesSocket(): void
    {
        $client = new LifecycleStatusDouble();

        $client->fetch();
        $client->failNextFetch = true;

        try {
            $client->fetch();
            self::fail('Expected fetch to fail.');
        } catch (ReceiveStatusException) {
            self::assertSame(StatusState::Failed, $client->status());
            self::assertFalse($client->isConnected());
        }

        $this->expectException(NotConnectedException::class);

        $client->getInfo();
    }

    public function testJavaStatusPlayersRemainAvailableAfterDisconnect(): void
    {
        $client = new JavaStatusDouble();

        $client->fetch();
        $client->disconnect();

        self::assertSame(StatusState::Fetched, $client->status());
        self::assertSame(['Steve'], $client->getPlayers());
    }

    public function testJavaStatusPlayersAreClearedAfterFailedRefresh(): void
    {
        $client = new JavaStatusDouble();

        $client->fetch();
        $client->failNextFetch = true;

        try {
            $client->fetch();
            self::fail('Expected fetch to fail.');
        } catch (ReceiveStatusException) {
            self::assertSame(StatusState::Failed, $client->status());
        }

        $this->expectException(NotConnectedException::class);

        $client->getPlayers();
    }

    public function testUdpFetchUsesStatusLifecycle(): void
    {
        $client = new JavaQueryDouble();

        $client->fetch();

        self::assertSame(StatusState::Fetched, $client->status());
        self::assertTrue($client->isConnected());
        self::assertSame(['hostname' => 'query'], $client->getInfo());
    }

    public function testUdpFetchClosesSocketAfterFailure(): void
    {
        $client = new JavaQueryDouble();
        $client->failNextFetch = true;

        try {
            $client->fetch();
            self::fail('Expected fetch to fail.');
        } catch (ReceiveStatusException) {
            self::assertSame(StatusState::Failed, $client->status());
            self::assertFalse($client->isConnected());
        }
    }
}

final class LifecycleStatusDouble extends AbstractStatus
{
    public bool $failNextFetch = false;

    public int $connectCount = 0;

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
        return (string)($this->getInfo()['motd'] ?? '');
    }

    protected function _connect(string $host, int $port): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->connectCount++;
        $this->socket = $socket;
    }

    protected function getStatus(): void
    {
        if ($this->failNextFetch) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->info = ['motd' => 'ok'];
    }
}

final class JavaStatusDouble extends MinecraftJavaStatus
{
    public bool $failNextFetch = false;

    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->socket = $socket;
    }

    protected function getStatus(): void
    {
        if ($this->failNextFetch) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->info = ['players' => ['online' => 1, 'max' => 20], 'favicon' => 'test-icon'];
        $this->players = ['Steve'];
    }
}

final class JavaQueryDouble extends MinecraftJavaQuery
{
    public bool $failNextFetch = false;

    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->socket = $socket;
    }

    protected function getStatus(): void
    {
        if ($this->failNextFetch) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->info = ['hostname' => 'query'];
        $this->players = ['Steve'];
    }
}

final class BedrockStatusDouble extends MinecraftBedrockStatus
{
    public function __construct()
    {
        parent::__construct('example.org', 19132, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->socket = $socket;
    }

    protected function getStatus(): void
    {
        $this->info = ['protocol' => 671];
    }
}

final class LegacyJavaStatusDouble extends MinecraftJavaPreOld17Status
{
    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    protected function _connect(string $host, int $port): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->socket = $socket;
    }

    protected function getStatus(): void
    {
        $this->info = ['version' => ['protocol' => 47]];
    }
}
