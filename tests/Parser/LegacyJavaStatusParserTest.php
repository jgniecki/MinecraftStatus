<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Parser\LegacyJavaStatusParser;
use PHPUnit\Framework\TestCase;

final class LegacyJavaStatusParserTest extends TestCase
{
    public function testParsesModernLegacyFormat(): void
    {
        $parser = new LegacyJavaStatusParser();

        $result = $parser->parse($this->legacyPacket("\u{00A7}1\x0047\x001.8.9\x00Legacy MOTD\x005\x0020"));

        self::assertSame('Legacy MOTD', $result['description']['text']);
        self::assertSame(5, $result['players']['online']);
        self::assertSame(20, $result['players']['max']);
        self::assertSame(47, $result['version']['protocol']);
        self::assertSame('1.8.9', $result['version']['name']);
    }

    public function testParsesOlderSectionSeparatedFormat(): void
    {
        $parser = new LegacyJavaStatusParser();

        $result = $parser->parse($this->legacyPacket("Old MOTD\u{00A7}3\u{00A7}10"));

        self::assertSame('Old MOTD', $result['description']['text']);
        self::assertSame(3, $result['players']['online']);
        self::assertSame(10, $result['players']['max']);
    }

    public function testEmptyPayloadThrowsProtocolException(): void
    {
        $parser = new LegacyJavaStatusParser();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $parser->parse('');
    }

    public function testShortPayloadThrowsProtocolException(): void
    {
        $parser = new LegacyJavaStatusParser();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $parser->parse("\xFF\x00\x01");
    }

    public function testPayloadWithoutLegacyPacketByteThrowsProtocolException(): void
    {
        $parser = new LegacyJavaStatusParser();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $parser->parse("\x00\x00\x00\x00");
    }

    public function testInvalidUtf16PayloadThrowsInvalidResponseException(): void
    {
        $parser = new LegacyJavaStatusParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $parser->parse("\xFF\x00\x00\x00");
    }

    public function testDeclaredPayloadLengthMismatchThrowsInvalidResponseException(): void
    {
        $parser = new LegacyJavaStatusParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to receive status.');

        $parser->parse("\xFF\x00\x02\x00\x41");
    }

    private function legacyPacket(string $decodedPayload): string
    {
        $payload = iconv('UTF-8', 'UTF-16BE', $decodedPayload);

        self::assertIsString($payload);

        return "\xFF" . pack('n', (int)(strlen($payload) / 2)) . $payload;
    }
}
