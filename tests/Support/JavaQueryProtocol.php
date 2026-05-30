<?php declare(strict_types=1);

namespace DevLancer\MinecraftStatus\Tests\Support;

final class JavaQueryProtocol
{
    public const SESSION_ID = "\x01\x02\x03\x04";

    public static function challengeResponse(int $challengeToken, string $sessionId = self::SESSION_ID): string
    {
        return "\x09" . $sessionId . (string)$challengeToken . "\x00";
    }

    /**
     * @param array<string, string> $info
     * @param list<string> $players
     */
    public static function fullStatResponse(array $info, array $players, string $sessionId = self::SESSION_ID): string
    {
        $keyValueSection = '';
        foreach ($info as $key => $value) {
            $keyValueSection .= $key . "\x00" . $value . "\x00";
        }

        return "\x00"
            . $sessionId
            . "splitnum\x00\x80\x00"
            . $keyValueSection
            . "\x00"
            . "\x01player_\x00\x00"
            . ($players === [] ? '' : implode("\x00", $players) . "\x00")
            . "\x00";
    }

    /**
     * @return array{magic: string, type: int, sessionId: string}
     */
    public static function decodeRequestHeader(string $request): array
    {
        return [
            'magic' => substr($request, 0, 2),
            'type' => isset($request[2]) ? ord($request[2]) : -1,
            'sessionId' => substr($request, 3, 4),
        ];
    }

    /**
     * @return array{magic: string, type: int, sessionId: string, challengeToken: int, padding: string}
     */
    public static function decodeFullStatRequest(string $request): array
    {
        $header = self::decodeRequestHeader($request);
        $token = unpack('N', substr($request, 7, 4));

        return [
            'magic' => $header['magic'],
            'type' => $header['type'],
            'sessionId' => $header['sessionId'],
            'challengeToken' => is_array($token) ? (int)$token[1] : 0,
            'padding' => substr($request, 11),
        ];
    }
}
