<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Parser;

use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Parser\BedrockStatusParser;
use PHPUnit\Framework\TestCase;

final class BedrockStatusParserTest extends TestCase
{
    private const OFFLINE_MESSAGE_DATA_ID = "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE\xFD\xFD\xFD\xFD\x12\x34\x56\x78";

    public function testParsesValidPong(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20;123;world;Survival;1;19132;19133;');

        $result = $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);

        self::assertSame('MCPE', $result['game_id']);
        self::assertSame('Bedrock Server', $result['hostname']);
        self::assertSame(671, $result['protocol']);
        self::assertSame('1.20.80', $result['version']);
        self::assertSame(5, $result['numplayers']);
        self::assertSame(20, $result['maxplayers']);
        self::assertSame(19132, $result['ipv4port']);
        self::assertSame(19133, $result['ipv6port']);
    }

    public function testParsesHostnameContainingSemicolons(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Name;With;Semicolon;671;1.20.80;5;20;123;world;Survival;1;19132;19133;');

        $result = $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);

        self::assertSame('Name;With;Semicolon', $result['hostname']);
        self::assertSame(671, $result['protocol']);
    }

    public function testInvalidFirstByteThrowsProtocolException(): void
    {
        $parser = new BedrockStatusParser();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('First byte is not ID_UNCONNECTED_PONG.');

        $parser->parse("\x00", self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testInvalidMagicBytesThrowsProtocolException(): void
    {
        $parser = new BedrockStatusParser();
        $payload = "\x1C" . str_repeat("\x00", 16) . str_repeat("\x01", 16);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Magic bytes do not match.');

        $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);
    }

    private function pongPayload(string $body): string
    {
        return "\x1C" . str_repeat("\x00", 16) . self::OFFLINE_MESSAGE_DATA_ID . "\x00\x00" . $body;
    }
}
