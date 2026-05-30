<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ProtocolException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Parser\BedrockStatusParser;
use DevLancer\MinecraftStatus\Result\BedrockStatusResult;
use InvalidArgumentException;

class MinecraftBedrockStatus extends AbstractStatus implements ProtocolInterface
{
    /**
     * QueryBedrock constructor.
     *
     * @inheritDoc
     * @throws InvalidArgumentException The $timeout must be greater than zero
     */
    public function __construct(string $host, int $port = 19132, int|float $timeout = 3, bool $resolveSRV = true)
    {
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }

    /**
     * @inheritDoc
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function fetch(): static
    {
        return $this->fetchWithConnection(function (): void {
            $this->_connect('udp://' . $this->host, $this->port);
            stream_set_blocking($this->socket, true);
        });
    }

    public function getProtocol(): int
    {
        return $this->getInfo()['protocol'];
    }

    protected function createResult(): BedrockStatusResult
    {
        return new BedrockStatusResult(
            $this->info,
            (string)($this->info['hostname'] ?? ''),
            (int)($this->info['numplayers'] ?? 0),
            (int)($this->info['maxplayers'] ?? 0),
            (int)($this->info['protocol'] ?? 0),
            isset($this->info['version']) ? (string)$this->info['version'] : null,
            isset($this->info['game_mode']) ? (string)$this->info['game_mode'] : null,
            isset($this->info['map']) ? (string)$this->info['map'] : null,
            isset($this->info['server_id']) ? (string)$this->info['server_id'] : null,
            (int)($this->info['ipv4port'] ?? 0),
            (int)($this->info['ipv6port'] ?? 0)
        );
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        $OFFLINE_MESSAGE_DATA_ID = pack('c*', 0x00, 0xFF, 0xFF, 0x00, 0xFE, 0xFE, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFD, 0x12, 0x34, 0x56, 0x78);

        $command = pack('cQ', 0x01, time());
        $command .= $OFFLINE_MESSAGE_DATA_ID;
        $command .= pack('Q', 2);
        $length = strlen($command);

        if ($length !== fwrite($this->socket, $command, $length)) {
            throw new ReceiveStatusException("Failed to write on socket.");
        }

        $data = fread($this->socket, 4096);

        if ($data === false) {
            throw new ReceiveStatusException("Failed to read from socket.");
        }

        $info = $this->createBedrockStatusParser()->parse($data, $OFFLINE_MESSAGE_DATA_ID);
        $this->info = $this->encoding($info);
    }

    /**
     * @throws ProtocolException
     */
    protected function validatePong(string $data, string $offlineMessageDataId): void
    {
        $this->createBedrockStatusParser()->validatePong($data, $offlineMessageDataId);
    }

    /**
     * @param string $data
     * @return array<string, int|string|null>
     */
    protected function resolveStatus(string $data): array
    {
        return $this->createBedrockStatusParser()->resolveStatus($data);
    }

    protected function createBedrockStatusParser(): BedrockStatusParser
    {
        return new BedrockStatusParser();
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getCountPlayers(): int
    {
        return (int)($this->getInfo()['numplayers'] ?? 0);
    }

    /**
     * @return int
     * @throws NotConnectedException
     */
    public function getMaxPlayers(): int
    {
        return (int)($this->getInfo()['maxplayers'] ?? 0);
    }

    /**
     * @return string
     * @throws NotConnectedException
     */
    public function getMotd(): string
    {
        return $this->getInfo()['hostname'] ?? "";
    }
}

/**
 * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftBedrockStatus instead.
 */
final class QueryBedrock extends MinecraftBedrockStatus
{
    /**
     * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftBedrockStatus instead.
     */
    public function __construct(string $host, int $port = 19132, int|float $timeout = 3, bool $resolveSRV = true)
    {
        trigger_error(
            sprintf('Class %s is deprecated and will be removed in future versions. Please use class %s instead.', __CLASS__, MinecraftBedrockStatus::class),
            E_USER_DEPRECATED
        );
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }
}

