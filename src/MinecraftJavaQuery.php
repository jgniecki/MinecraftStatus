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
use DevLancer\MinecraftStatus\Parser\JavaQueryParser;
use DevLancer\MinecraftStatus\Result\JavaQueryResult;

class MinecraftJavaQuery extends AbstractStatus implements MinecraftJavaQueryInterface
{
    private const SESSION_ID = "\x01\x02\x03\x04";

    /**
     * @var string[]
     */
    protected array $players = [];

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

    protected function resetState(): void
    {
        parent::resetState();
        $this->players = [];
    }

    protected function createResult(): JavaQueryResult
    {
        return new JavaQueryResult(
            $this->info,
            (string)($this->info['hostname'] ?? ''),
            (int)($this->info['numplayers'] ?? 0),
            (int)($this->info['maxplayers'] ?? 0),
            (string)($this->info['hostip'] ?? ''),
            $this->players
        );
    }


    /**
     * @inheritDoc
     * @throws NotConnectedException
     */
    public function getPlayers(): array
    {
        $this->assertFetched();

        return $this->players;
    }

    /**
     * @throws ReceiveStatusException
     */
    protected function getStatus(): void
    {
        $append = $this->getChallenge() . pack('c*', 0x00, 0x00, 0x00, 0x00);
        $data = $this->writeData(0x00, $append);

        if (!$data) {
            throw new ProtocolException('Failed to receive status.');
        }

        $result = $this->createJavaQueryParser()->parse($data);
        $this->players = $this->encoding($result['players']);
        $this->info = $this->encoding($result['info']);
        $this->info['hostip'] = gethostbyname($this->host);
    }

    /**
     * @param string $data
     * @return array
     */
    protected function resolvePlayerList(string $data): array
    {
        return $this->encoding($this->createJavaQueryParser()->resolvePlayerList($data));
    }

    protected function createJavaQueryParser(): JavaQueryParser
    {
        return new JavaQueryParser();
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
     * @return string
     * @throws ReceiveStatusException
     */
    protected function getChallenge(): string
    {
        $data = $this->writeData(0x09);

        if (!$data) {
            throw new ProtocolException('Failed to receive challenge.');
        }

        $challenge = rtrim($data, "\x00");
        if (!is_numeric($challenge)) {
            throw new ProtocolException('Failed to receive challenge.');
        }

        return pack('N', (int)$challenge);
    }

    /**
     * @param int $command
     * @param string $append
     * @return string|null
     * @throws ReceiveStatusException
     */
    protected function writeData(int $command, string $append = ""): ?string
    {
        $packet = pack('c*', 0xFE, 0xFD, $command) . self::SESSION_ID . $append;
        $length = strlen($packet);

        if ($length !== fwrite($this->socket, $packet, $length)) {
            throw new ReceiveStatusException("Failed to write on socket.");
        }

        $data = fread($this->socket, 4096);

        if ($data === false) {
            throw new ReceiveStatusException("Failed to read from socket.");
        }

        if (strlen($data) < 5 || ord($data[0]) !== $command || substr($data, 1, 4) !== self::SESSION_ID) {
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
    public function __construct(string $host, int $port = 25565, int|float $timeout = 3, bool $resolveSRV = true)
    {
        trigger_error(
            sprintf('Class %s is deprecated and will be removed in future versions. Please use class %s instead.', __CLASS__, MinecraftJavaQuery::class),
            E_USER_DEPRECATED
        );
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }
}

