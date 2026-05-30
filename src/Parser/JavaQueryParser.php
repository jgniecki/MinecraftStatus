<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;

final class JavaQueryParser
{
    private const PLAYER_SECTION_SEPARATOR = "\x00\x00\x01player_\x00\x00";

    /**
     * @return array{info: array<string, string>, players: string[]}
     * @throws InvalidResponseException
     */
    public function parse(string $payload): array
    {
        $data = substr($payload, 11);
        $sections = explode(self::PLAYER_SECTION_SEPARATOR, $data);

        if (count($sections) !== 2) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        return [
            'info' => $this->parseInfo($sections[0]),
            'players' => $this->resolvePlayerList($sections[1]),
        ];
    }

    /**
     * @return string[]
     */
    public function resolvePlayerList(string $data): array
    {
        $players = substr($data, 0, -2);

        if ($players === '') {
            return [];
        }

        return explode("\x00", $players);
    }

    /**
     * @return array<string, string>
     * @throws InvalidResponseException
     */
    private function parseInfo(string $data): array
    {
        $fields = explode("\x00", $data);
        if (end($fields) === '') {
            array_pop($fields);
        }

        if (count($fields) % 2 !== 0) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        $info = [];
        for ($i = 1; $i < count($fields); $i += 2) {
            $info[$fields[$i - 1]] = $fields[$i];
        }

        return $info;
    }
}
