<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Exception;

use DevLancer\MinecraftStatus\AbstractStatus;
use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Exception\StatusNotResolvedException;
use DevLancer\MinecraftStatus\Exception\TimeoutException;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    public function testNotConnectedExceptionKeepsOldTypeAndAddsStatusNotResolvedType(): void
    {
        $exception = new NotConnectedException('The status has not been fetched.');

        self::assertInstanceOf(NotConnectedException::class, $exception);
        self::assertInstanceOf(StatusNotResolvedException::class, $exception);
    }

    public function testGetInfoBeforeFetchCanBeCaughtAsStatusNotResolvedException(): void
    {
        $client = new UnfetchedStatusDouble();

        try {
            $client->getInfo();
            self::fail('Expected getInfo() to fail before fetch().');
        } catch (StatusNotResolvedException $exception) {
            self::assertInstanceOf(NotConnectedException::class, $exception);
        }
    }

    public function testTimeoutExceptionKeepsReceiveStatusCompatibility(): void
    {
        self::assertInstanceOf(ReceiveStatusException::class, new TimeoutException());
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

final class UnfetchedStatusDouble extends AbstractStatus
{
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

    protected function getStatus(): void
    {
        $this->info = [];
    }
}
