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
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;

class MinecraftJavaQuery extends AbstractStatus implements MinecraftJavaQueryInterface
{
    /**
     * @var string[]
     */
    protected array $players = [];

    /**
     * @inheritDoc
     * @return MinecraftJavaQuery
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function connect(): StatusInterface
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }

        $this->_connect('udp://' . $this->host, $this->port);
        stream_set_blocking($this->socket, true);
        $this->getStatus();
        return $this;
    }


    /**
     * @inheritDoc
     * @throws NotConnectedException
     */
    public function getPlayers(): array
    {
        if (!$this->isConnected()) {
            throw new NotConnectedException('The connection has not been established.');
        }

        return $this->players;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        $append = $this->getChallenge() . pack('c*', 0x00, 0x00, 0x00, 0x00);
        $data = $this->writeData(0x00, $append);

        if (!$data) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $data = substr($data, 11);
        $data = explode("\x00\x00\x01player_\x00\x00", $data);

        if (count($data) !== 2) {
            throw new ReceiveStatusException('Failed to parse server\'s response.');
        }

        if (is_string($data[1])) {
            $this->players = $this->resolvePlayerList($data[1]);
        }

        $data = explode("\x00", $data[0]);
        $info = [];
        for ($i = 1; $i < count($data); $i += 2) {
            $info[$data[$i - 1]] = $data[$i];
        }

        $this->info = $this->encoding($info);
        $this->info['hostip'] = gethostbyname($this->host);
    }

    /**
     * @param string $data
     * @return array
     */
    protected function resolvePlayerList(string $data): array
    {
        $players = substr($data, 0, -2);
        $players = explode("\x00", $players);
        return $this->encoding($players);
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

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return string
     * @throws ReceiveStatusException
     */
    protected function getChallenge(): string
    {
        $data = $this->writeData(0x09);

        if (!$data) {
            throw new ReceiveStatusException('Failed to receive challenge.');
        }

        return pack('N', $data);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @param int $command
     * @param string $append
     * @return string|null
     * @throws ReceiveStatusException
     */
    protected function writeData(int $command, string $append = ""): ?string
    {
        $command = pack('c*', 0xFE, 0xFD, $command, 0x01, 0x02, 0x03, 0x04) . $append;
        $length = strlen($command);

        if ($length !== fwrite($this->socket, $command, $length)) {
            throw new ReceiveStatusException("Failed to write on socket.");
        }

        $data = fread($this->socket, 4096);

        if ($data === false) {
            throw new ReceiveStatusException("Failed to read from socket.");
        }

        if (strlen($data) < 5 || $data[0] != $command[2]) {
            return null;
        }

        return substr($data, 5);
    }
}

/**
 * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaQuery instead.
 */
final class Query extends MinecraftJavaQuery
{
    /**
     * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaQuery instead.
     */
    public function __construct(string $host, int $port = 25565, int $timeout = 3, bool $resolveSRV = true)
    {
        trigger_error(
            sprintf('Class %s is deprecated and will be removed in future versions. Please use class %s instead.', __CLASS__, MinecraftJavaQuery::class),
            E_USER_DEPRECATED
        );
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }
}