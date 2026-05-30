<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Parser;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;

final class LegacyJavaStatusParser
{
    /**
     * @return array<string, mixed>
     * @throws InvalidResponseException
     * @throws ProtocolException
     */
    public function parse(string $payload): array
    {
        $this->validateLegacyPacket($payload);

        $payload = substr($payload, 3);
        $payload = $this->decodeLegacyPayload($payload);

        return $this->parseDecodedPayload($payload);
    }

    /**
     * @throws ProtocolException
     */
    public function validateLegacyPacket(string $data): void
    {
        if ($data === '' || strlen($data) < 4 || $data[0] !== "\xFF") {
            throw new ProtocolException('Failed to receive status.');
        }
    }

    /**
     * @throws InvalidResponseException
     */
    public function decodeLegacyPayload(string $data): string
    {
        $decoded = @iconv('UTF-16BE', 'UTF-8', $data);

        if ($decoded === false) {
            throw new InvalidResponseException('Failed to receive status.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDecodedPayload(string $data): array
    {
        if (isset($data[1], $data[2]) && $data[1] === "\xA7" && $data[2] === "\x31") {
            $data = explode("\x00", $data);

            return [
                'description' => [
                    'text' => $data[3] ?? null,
                ],
                'players' => [
                    'max' => (int)($data[5] ?? 0),
                    'online' => (int)($data[4] ?? 0),
                ],
                'version' => [
                    'name' => $data[2] ?? null,
                    'protocol' => (int)($data[1] ?? 0),
                ],
            ];
        }

        $data = explode("\xA7", $data);

        return [
            'description' => [
                'text' => substr($data[0], 0, -1),
            ],
            'players' => [
                'max' => (int)($data[2] ?? 0),
                'online' => (int)($data[1] ?? 0),
            ],
        ];
    }
}
