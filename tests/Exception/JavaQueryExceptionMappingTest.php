<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Exception;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\MinecraftJavaQuery;
use PHPUnit\Framework\TestCase;

final class JavaQueryExceptionMappingTest extends TestCase
{
    public function testGetChallengeWithoutDataThrowsProtocolException(): void
    {
        $client = new JavaQueryExceptionMappingDouble();
        $client->useParentChallenge = true;
        $client->writeDataResponses = [null];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive challenge.');

        $client->getChallengeForTest();
    }

    public function testGetStatusWithoutDataThrowsProtocolException(): void
    {
        $client = new JavaQueryExceptionMappingDouble();
        $client->writeDataResponses = [null];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $client->getStatusForTest();
    }

    public function testGetStatusWithInvalidPlayerSectionThrowsInvalidResponseException(): void
    {
        $client = new JavaQueryExceptionMappingDouble();
        $client->writeDataResponses = [str_repeat("\x00", 11) . 'hostname' . "\x00" . 'server'];

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $client->getStatusForTest();
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

final class JavaQueryExceptionMappingDouble extends MinecraftJavaQuery
{
    /**
     * @var list<string|null>
     */
    public array $writeDataResponses = [];

    public bool $useParentChallenge = false;

    public function __construct()
    {
        parent::__construct('example.org', 25565, 3, false);
    }

    public function getChallengeForTest(): string
    {
        return $this->getChallenge();
    }

    public function getStatusForTest(): void
    {
        $this->getStatus();
    }

    protected function getChallenge(): string
    {
        if ($this->useParentChallenge) {
            return parent::getChallenge();
        }

        return "\x00\x00\x00\x00";
    }

    protected function writeData(int $command, string $append = ""): ?string
    {
        return array_shift($this->writeDataResponses);
    }
}
