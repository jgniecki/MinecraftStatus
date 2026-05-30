<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Parser\JavaStatusParser;
use PHPUnit\Framework\TestCase;

final class JavaStatusParserTest extends TestCase
{
    public function testParsesStatusWithStringDescriptionAndPlayers(): void
    {
        $parser = new JavaStatusParser();
        $payload = json_encode([
            'description' => 'Test server',
            'players' => [
                'online' => 1,
                'max' => 20,
                'sample' => [
                    ['name' => 'Steve', 'id' => 'uuid'],
                ],
            ],
            'version' => [
                'name' => '1.20.4',
                'protocol' => 765,
            ],
        ]);

        self::assertIsString($payload);

        $result = $parser->parse($payload);

        self::assertSame('Test server', $result['info']['description']);
        self::assertSame([['name' => 'Steve', 'id' => 'uuid']], $result['players']);
    }

    public function testParsesStatusWithStructuredDescription(): void
    {
        $parser = new JavaStatusParser();
        $payload = json_encode([
            'description' => ['text' => 'Structured MOTD'],
            'players' => ['online' => 0, 'max' => 20],
        ]);

        self::assertIsString($payload);

        $result = $parser->parse($payload);

        self::assertSame(['text' => 'Structured MOTD'], $result['info']['description']);
        self::assertSame([], $result['players']);
    }

    public function testInvalidJsonThrowsInvalidResponseException(): void
    {
        $parser = new JavaStatusParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('JSON parsing failed:');

        $parser->parse('{invalid');
    }

    public function testJsonThatIsNotArrayThrowsInvalidResponseException(): void
    {
        $parser = new JavaStatusParser();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('The server did not return the information');

        $parser->parse('"not an object"');
    }
}
