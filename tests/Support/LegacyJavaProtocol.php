<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

final class LegacyJavaProtocol
{
    public static function response(string $decodedPayload): string
    {
        $payload = iconv('UTF-8', 'UTF-16BE', $decodedPayload);

        if (!is_string($payload)) {
            $payload = '';
        }

        return "\xFF" . pack('n', (int)(strlen($payload) / 2)) . $payload;
    }
}
