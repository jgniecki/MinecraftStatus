<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;

final class JavaStatusParser
{
    /**
     * @return array{info: array<string, mixed>, players: array}
     * @throws InvalidResponseException
     */
    public function parse(string $payload): array
    {
        $result = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidResponseException('JSON parsing failed: ' . json_last_error_msg());
        }

        if (!is_array($result)) {
            throw new InvalidResponseException('The server did not return the information');
        }

        return [
            'info' => $result,
            'players' => $this->resolvePlayerList($result),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array
     */
    public function resolvePlayerList(array $data): array
    {
        $players = [];
        if (isset($data['players']['sample']) && is_array($data['players']['sample'])) {
            foreach ($data['players']['sample'] as $value) {
                $players[] = $value;
            }
        }

        return $players;
    }
}
