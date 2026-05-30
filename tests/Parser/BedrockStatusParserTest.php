<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
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
        self::assertSame('1', $result['game_mode_numeric']);
        self::assertSame('1', $result['nintendo_limited']);
        self::assertSame(19132, $result['ipv4port']);
        self::assertSame(19133, $result['ipv6port']);
        self::assertNull($result['extra']);
    }

    public function testParsesValidPongWithoutTrailingTerminator(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20;123;world;Survival;1;19132;19133');

        $result = $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);

        self::assertSame('Bedrock Server', $result['hostname']);
        self::assertSame(671, $result['protocol']);
    }

    public function testHostnameContainingSemicolonsThrowsInvalidResponseException(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Name;With;Semicolon;671;1.20.80;5;20;123;world;Survival;1;19132;19133;');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testTooFewStatusFieldsThrowsInvalidResponseException(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testNonEmptyThirteenthStatusFieldThrowsInvalidResponseException(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20;123;world;Survival;1;19132;19133;extra');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);
    }

    public function testIgnoresDataAfterLengthPrefixedStatusPayload(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20;123;world;Survival;1;19132;19133;', null, 'ignored;tail');

        $result = $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);

        self::assertSame('Bedrock Server', $result['hostname']);
        self::assertSame(671, $result['protocol']);
    }

    public function testStatusLengthLongerThanPayloadThrowsInvalidResponseException(): void
    {
        $parser = new BedrockStatusParser();
        $payload = $this->pongPayload('MCPE;Bedrock Server;671;1.20.80;5;20;123;world;Survival;1;19132;19133;', 4096);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse($payload, self::OFFLINE_MESSAGE_DATA_ID);
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

    private function pongPayload(string $body, ?int $declaredLength = null, string $trailingData = ''): string
    {
        return "\x1C" . str_repeat("\x00", 16) . self::OFFLINE_MESSAGE_DATA_ID . pack('n', $declaredLength ?? strlen($body)) . $body . $trailingData;
    }
}
