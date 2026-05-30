<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Exception\InvalidResponseException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Result\LegacyJavaStatusResult;

class MinecraftJavaPreOld17Status extends AbstractStatus implements ProtocolInterface
{
    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getCountPlayers(): int
    {
        return (int)($this->getInfo()['players']['online'] ?? 0);
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getMaxPlayers(): int
    {
        return (int)($this->getInfo()['players']['max'] ?? 0);
    }

    /**
     * Returns the server protocol number
     *
     * @return int
     * @throws NotConnectedException
     */
    public function getProtocol(): int
    {
        return (int)($this->getInfo()['version']['protocol'] ?? 0);
    }

    /**
     * @return string
     * @throws NotConnectedException
     */
    public function getMotd(): string
    {
        return $this->getInfo()['description']['text'] ?? "";
    }

    protected function createResult(): LegacyJavaStatusResult
    {
        $description = $this->info['description'] ?? [];
        $players = $this->info['players'] ?? [];
        $version = $this->info['version'] ?? [];

        return new LegacyJavaStatusResult(
            $this->info,
            (string)(is_array($description) ? ($description['text'] ?? '') : ''),
            (int)(is_array($players) ? ($players['online'] ?? 0) : 0),
            (int)(is_array($players) ? ($players['max'] ?? 0) : 0),
            (int)(is_array($version) ? ($version['protocol'] ?? 0) : 0),
            is_array($version) && isset($version['name']) ? (string)$version['name'] : null
        );
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     *
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        fwrite($this->socket, "\xFE\x01");
        $data = fread($this->socket, 512);
        if ($data === false) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->validateLegacyPacket($data);

        $data = substr($data, 3); // Strip packet header (kick message packet and short length)
        $data = $this->decodeLegacyPayload($data);

        // Are we dealing with Minecraft 1.4+ server?
        if ($data[1] === "\xA7" && $data[2] === "\x31") {
            $data = explode("\x00", $data);
            $result['description']['text'] = $data[3] ?? null;
            $result['players'] = [
                "max" => (int)($data[5] ?? 0),
                "online" => (int)($data[4] ?? 0),
            ];

            $result['version'] = [
                'name' => $data[2] ?? null,
                'protocol' => (int)($data[1] ?? 0)
            ];

            $this->info = $this->encoding($result);
            return;
        }

        $data = explode("\xA7", $data);
        $result['description']['text'] = substr($data[0], 0, -1);
        $result['players'] = [
            "max" => (int)($data[2] ?? 0),
            "online" => (int)($data[1] ?? 0),
        ];

        $this->info = $this->encoding($result);
    }

    /**
     * @throws ProtocolException
     */
    protected function validateLegacyPacket(string $data): void
    {
        if ($data === '' || strlen($data) < 4 || $data[0] !== "\xFF") {
            throw new ProtocolException('Failed to receive status.');
        }
    }

    /**
     * @throws InvalidResponseException
     */
    protected function decodeLegacyPayload(string $data): string
    {
        $decoded = @iconv('UTF-16BE', 'UTF-8', $data);

        if ($decoded === false) {
            throw new InvalidResponseException('Failed to receive status.');
        }

        return $decoded;
    }
}

/**
 * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status instead.
 */
final class PingPreOld17 extends MinecraftJavaPreOld17Status
{
    /**
     * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaPreOld17Status instead.
     */
    public function __construct(string $host, int $port = 25565, int|float $timeout = 3, bool $resolveSRV = true)
    {
        trigger_error(
            sprintf('Class %s is deprecated and will be removed in future versions. Please use class %s instead.', __CLASS__, MinecraftJavaPreOld17Status::class),
            E_USER_DEPRECATED
        );
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }
}

