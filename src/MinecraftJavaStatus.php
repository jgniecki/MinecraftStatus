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

class MinecraftJavaStatus extends AbstractStatus implements PlayerListInterface, FaviconInterface, DelayInterface, ProtocolInterface
{
    /**
     * @var string[]
     */
    protected array $players = [];

    protected int $delay = 0;

    /**
     * @inheritDoc
     * @return MinecraftJavaStatus
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function connect(): StatusInterface
    {
        parent::connect();
        return $this;
    }

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

    public function getDelay(): int
    {
        return $this->delay;
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
     * @return string
     * @throws NotConnectedException
     */
    public function getFavicon(): string
    {
        return $this->getInfo()['favicon'] ?? "";
    }

    /**
     * @return string
     * @throws NotConnectedException
     */
    public function getMotd(): string
    {
        $motd = $this->getInfo()['description'] ?? "";
        return (is_array($motd)) ? json_encode($motd) : $motd;
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @throws ReceiveStatusException*
     */
    protected function getStatus(): void
    {
        $data = "\x00"; // packet ID = 0 (varint)
        $data .= "\xff\xff\xff\xff\x0f"; //Protocol version (varint)
        $data .= pack('c', strlen($this->host)) . $this->host; // Server (varint len + UTF-8 addr)
        $data .= pack('n', $this->port); // Server port (unsigned short)
        $data .= "\x01"; // Next state: status (varint)
        $data = pack('c', strlen($data)) . $data; // prepend length of packet ID + data
        $timestart = microtime(true); // for read timeout purposes
        fwrite($this->socket, $data . "\x01\x00"); // handshake

        $length = $this->readVarInt(); // full packet length
        if ($length < 10) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $this->readVarInt(); // packet type, in server ping it's 0
        $length = $this->readVarInt(); // string length
        if ($length < 2) {
            throw new ReceiveStatusException('Failed to receive status.');
        }

        $data = "";

        do {
            if (microtime(true) - $timestart > $this->timeout) {
                throw new ReceiveStatusException('Server read timed out');
            }

            $remainder = $length - strlen($data);
            if ($remainder <= 0) {
                break;
            }

            $block = fread($this->socket, $remainder);
            if ($this->delay == 0) {
                $this->delay = (int)floor((microtime(true) - $timestart) * 1000);
            }

            if (!$block) {
                throw new ReceiveStatusException('Server returned too few data');
            }

            $data .= $block;
        } while (strlen($data) < $length);
        $result = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ReceiveStatusException('JSON parsing failed: ' . json_last_error_msg());
        }

        if (!is_array($result)) {
            throw new ReceiveStatusException('The server did not return the information');
        }

        $result = $this->encoding($result);
        $this->players = $this->resolvePlayerList($result);
        $this->info = $result;
    }

    /**
     * @param array $data <string, mixed>
     * @return array
     */
    protected function resolvePlayerList(array $data): array
    {
        $players = [];
        if (isset($data['players']['sample'])) {
            foreach ($data['players']['sample'] as $value) {
                $players[] = $value;
            }
        }

        return $players;
    }


    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @return int
     * @throws ReceiveStatusException
     */
    protected function readVarInt(): int
    {
        $i = 0;
        $j = 0;

        while (true) {
            $k = @fgetc($this->socket);
            if ($k === false) {
                return 0;
            }

            $k = ord($k);
            $i |= ($k & 0x7F) << $j++ * 7;

            if ($j > 5) {
                throw new ReceiveStatusException('VarInt too big');
            }

            if (($k & 0x80) != 128) {
                break;
            }
        }

        return $i;
    }
}

/**
 * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaStatus instead.
 */
final class Ping extends MinecraftJavaStatus
{
    /**
     * @deprecated Since version 3.1. Please use class DevLancer\MinecraftStatus\MinecraftJavaStatus instead.
     */
    public function __construct(string $host, int $port = 25565, int $timeout = 3, bool $resolveSRV = true)
    {
        trigger_error(
            sprintf('Class %s is deprecated and will be removed in future versions. Please use class %s instead.', __CLASS__, MinecraftJavaStatus::class),
            E_USER_DEPRECATED
        );
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }
}