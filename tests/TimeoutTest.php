<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests;

use DevLancer\MinecraftStatus\AbstractStatus;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimeoutTest extends TestCase
{
    public function testSetTimeoutAcceptsInteger(): void
    {
        $client = new TimeoutStatusDouble();

        $client->setTimeout(1);

        self::assertSame(1.0, $client->getTimeout());
    }

    public function testSetTimeoutAcceptsFloat(): void
    {
        $client = new TimeoutStatusDouble();

        $client->setTimeout(0.5);

        self::assertSame(0.5, $client->getTimeout());
    }

    public function testConstructorAcceptsFloatTimeout(): void
    {
        $client = new TimeoutStatusDouble(0.25);

        self::assertSame(0.25, $client->getTimeout());
    }

    public function testSetTimeoutRejectsZero(): void
    {
        $client = new TimeoutStatusDouble();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $client->setTimeout(0);
    }

    public function testSetTimeoutRejectsNegativeValue(): void
    {
        $client = new TimeoutStatusDouble();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $client->setTimeout(-1);
    }

    public function testStreamTimeoutHelperAcceptsSubSecondTimeout(): void
    {
        $client = new TimeoutStatusDouble(0.25);

        $client->openTestSocketWithAppliedTimeout();

        self::assertTrue($client->isConnected());
    }
}

final class TimeoutStatusDouble extends AbstractStatus
{
    public function __construct(int|float $timeout = 3)
    {
        parent::__construct('example.org', 25565, $timeout, false);
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

    public function openTestSocketWithAppliedTimeout(): void
    {
        $socket = fopen('php://memory', 'r+');

        if ($socket === false) {
            throw new ReceiveStatusException('Failed to open test socket.');
        }

        $this->socket = $socket;
        $this->applyStreamTimeout();
    }

    protected function getStatus(): void
    {
        $this->info = ['motd' => 'ok'];
    }
}
