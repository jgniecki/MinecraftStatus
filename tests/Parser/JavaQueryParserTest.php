<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Parser\JavaQueryParser;
use PHPUnit\Framework\TestCase;

final class JavaQueryParserTest extends TestCase
{
    public function testParsesStatusWithPlayers(): void
    {
        $parser = new JavaQueryParser();

        $result = $parser->parse($this->queryPayload(
            "hostname\x00Query Server\x00numplayers\x002\x00maxplayers\x0020",
            "Steve\x00Alex\x00\x00"
        ));

        self::assertSame('Query Server', $result['info']['hostname']);
        self::assertSame('2', $result['info']['numplayers']);
        self::assertSame('20', $result['info']['maxplayers']);
        self::assertSame(['Steve', 'Alex'], $result['players']);
    }

    public function testParsesStatusWithoutPlayers(): void
    {
        $parser = new JavaQueryParser();

        $result = $parser->parse($this->queryPayload(
            "hostname\x00Query Server",
            "\x00\x00"
        ));

        self::assertSame('Query Server', $result['info']['hostname']);
        self::assertSame([], $result['players']);
    }

    public function testMissingPlayerSectionThrowsInvalidResponseException(): void
    {
        $parser = new JavaQueryParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse(str_repeat("\x00", 11) . "hostname\x00Query Server");
    }

    public function testInvalidKeyValueStructureThrowsInvalidResponseException(): void
    {
        $parser = new JavaQueryParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Failed to parse server\'s response.');

        $parser->parse($this->queryPayload(
            "hostname\x00Query Server\x00numplayers",
            "\x00\x00"
        ));
    }

    private function queryPayload(string $info, string $players): string
    {
        return str_repeat("\x00", 11) . $info . "\x00\x00\x01player_\x00\x00" . $players;
    }
}
