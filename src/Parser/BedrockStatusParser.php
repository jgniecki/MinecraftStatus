<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;

final class BedrockStatusParser
{
    /**
     * @return array<string, int|string|null>
     * @throws InvalidResponseException
     * @throws ProtocolException
     */
    public function parse(string $payload, string $offlineMessageDataId): array
    {
        $this->validatePong($payload, $offlineMessageDataId);

        return $this->resolveStatus($payload);
    }

    /**
     * @throws ProtocolException
     */
    public function validatePong(string $data, string $offlineMessageDataId): void
    {
        if ($data === '' || $data[0] !== "\x1C") {
            throw new ProtocolException("First byte is not ID_UNCONNECTED_PONG.");
        }

        if (substr($data, 17, 16) !== $offlineMessageDataId) {
            throw new ProtocolException("Magic bytes do not match.");
        }
    }

    /**
     * @return array<string, int|string|null>
     * @throws InvalidResponseException
     */
    public function resolveStatus(string $data): array
    {
        $statusPayload = $this->readStatusPayload($data);
        $data = explode(';', $statusPayload);

        if (count($data) === 13 && end($data) === '') {
            array_pop($data);
        }

        if (count($data) !== 12) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        $gameModeNumeric = $data[9];

        return [
            'game_id' => $data[0],
            'hostname' => $data[1],
            'protocol' => (int)$data[2],
            'version' => $data[3],
            'numplayers' => (int)$data[4],
            'maxplayers' => (int)$data[5],
            'server_id' => $data[6],
            'map' => $data[7],
            'game_mode' => $data[8],
            'game_mode_numeric' => $gameModeNumeric,
            'nintendo_limited' => $gameModeNumeric,
            'ipv4port' => (int)$data[10],
            'ipv6port' => (int)$data[11],
            'extra' => null,
        ];
    }

    /**
     * @throws InvalidResponseException
     */
    private function readStatusPayload(string $data): string
    {
        if (strlen($data) < 35) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        $length = unpack('n', substr($data, 33, 2));
        if (!is_array($length)) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        $length = (int)$length[1];
        $payload = substr($data, 35, $length);
        if (strlen($payload) !== $length) {
            throw new InvalidResponseException('Failed to parse server\'s response.');
        }

        return $payload;
    }
}
