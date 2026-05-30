<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Parser;

use DevLancer\MinecraftStatus\Exception\ProtocolException;

final class BedrockStatusParser
{
    /**
     * @return array<string, int|string|null>
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
     */
    public function resolveStatus(string $data): array
    {
        $data = substr($data, 35);
        $data = explode(';', $data);
        $offset = count($data) - 13;
        if ($offset < 0) {
            $offset = 0;
        }

        $hostname = [];
        for ($i = 0; $i <= $offset; $i++) {
            $hostname[] = $data[1 + $i] ?? '';
        }

        return [
            'game_id' => $data[0] ?? null,
            'hostname' => implode(";", $hostname),
            'protocol' => (int)($data[2 + $offset] ?? 0),
            'version' => $data[3 + $offset] ?? null,
            'numplayers' => (isset($data[4 + $offset])) ? (int)$data[4 + $offset] : 0,
            'maxplayers' => (isset($data[5 + $offset])) ? (int)$data[5 + $offset] : 0,
            'server_id' => $data[6 + $offset] ?? null,
            'map' => $data[7 + $offset] ?? null,
            'game_mode' => $data[8 + $offset] ?? null,
            'nintendo_limited' => $data[9 + $offset] ?? null,
            'ipv4port' => (isset($data[10 + $offset])) ? (int)$data[10 + $offset] : 0,
            'ipv6port' => (isset($data[11 + $offset])) ? (int)$data[11 + $offset] : 0,
            'extra' => $data[12 + $offset] ?? null,
        ];
    }
}
