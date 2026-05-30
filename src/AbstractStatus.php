<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Dns\SrvResolver;
use DevLancer\MinecraftStatus\Exception\ConnectionException;
use DevLancer\MinecraftStatus\Exception\NotConnectedException;
use DevLancer\MinecraftStatus\Exception\ReceiveStatusException;
use DevLancer\MinecraftStatus\Result\GenericStatusResult;
use DevLancer\MinecraftStatus\Result\StatusResultInterface;
use InvalidArgumentException;
use Throwable;

abstract class AbstractStatus implements StatusInterface
{
    /**
     * @var resource|null
     */
    protected $socket = null;

    /**
     * @var string
     */
    protected string $host;

    /**
     * @var int
     */
    protected int $port;

    protected float $timeout;

    /**
     * @var bool
     */
    protected bool $resolveSRV;

    /**
     * @var array<string, mixed>
     */
    protected array $info = [];

    protected ?StatusResultInterface $result = null;

    protected StatusState $statusState = StatusState::Idle;

    /**
     * @var string
     */
    protected string $encoding = 'UTF-8';

    /**
     * @param string $host
     * @param int $port
     * @param int|float $timeout
     * @param bool $resolveSRV
     * @throws InvalidArgumentException The $timeout must be greater than zero
     */
    public function __construct(string $host, int $port = 25565, int|float $timeout = 3, bool $resolveSRV = true)
    {
        $this->resolveSRV = $resolveSRV;

        if ($this->resolveSRV) {
            $resolve = $this->createSrvResolver()->resolve($host, $port);
            $host = $resolve['host'];
            $port = $resolve['port'];
        }

        $this->host = $host;
        $this->port = $port;

        $this->setTimeout($timeout);
    }

    /**
     * @inheritDoc
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    public function fetch(): static
    {
        return $this->fetchWithConnection(function (): void {
            $this->_connect($this->host, $this->port);
        });
    }

    public function connect(): StatusInterface
    {
        return $this->fetch();
    }

    public function status(): StatusState
    {
        return $this->statusState;
    }

    /**
     * @param callable(): void $openConnection
     * @throws ConnectionException Thrown when failed to connect to resource
     * @throws ReceiveStatusException Thrown when the status has not been obtained or resolved
     */
    protected function fetchWithConnection(callable $openConnection): static
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }

        $this->resetState();
        $this->statusState = StatusState::Fetching;

        try {
            $openConnection();
            $this->getStatus();
            $this->result = $this->createResult();
            $this->statusState = StatusState::Fetched;
        } catch (Throwable $exception) {
            $this->resetState();
            $this->statusState = StatusState::Failed;
            $this->disconnect();
            throw $exception;
        }

        return $this;
    }

    abstract protected function getStatus();

    protected function createSrvResolver(): SrvResolver
    {
        return new SrvResolver();
    }

    protected function resetState(): void
    {
        $this->info = [];
        $this->result = null;
    }

    /**
     * @throws NotConnectedException
     */
    protected function assertFetched(): void
    {
        if ($this->statusState !== StatusState::Fetched) {
            throw new NotConnectedException('The status has not been fetched.');
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    public function disconnect(): void
    {
        if ($this->isConnected()) {
            if (fclose($this->socket)) {
                $this->socket = null;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        return is_resource($this->socket);
    }

    /**
     * @inheritDoc
     * @throws NotConnectedException
     */
    public function getInfo(): array
    {
        $this->assertFetched();

        return $this->info;
    }

    /**
     * @inheritDoc
     * @throws NotConnectedException
     */
    public function getResult(): StatusResultInterface
    {
        $this->assertFetched();

        if ($this->result === null) {
            throw new NotConnectedException('The status has not been fetched.');
        }

        return $this->result;
    }

    protected function createResult(): StatusResultInterface
    {
        return new GenericStatusResult(
            $this->info,
            (string)($this->info['motd'] ?? ''),
            (int)($this->info['players']['online'] ?? $this->info['numplayers'] ?? 0),
            (int)($this->info['players']['max'] ?? $this->info['maxplayers'] ?? 0)
        );
    }

    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     */
    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * @return float
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException The timeout must be greater than zero.
     */
    public function setTimeout(int|float $timeout): void
    {
        if ($timeout <= 0) {
            throw new InvalidArgumentException("The timeout must be greater than zero.");
        }

        $this->timeout = (float)$timeout;
    }

    /**
     * @return bool
     */
    public function isResolveSRV(): bool
    {
        return $this->resolveSRV;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @throws ConnectionException Thrown when failed to connect to resource
     */
    protected function _connect(string $host, int $port): void
    {
        $socket = @fsockopen($host, $port, $err_no, $err_str, $this->timeout);

        if ($err_no || !is_resource($socket)) {
            throw new ConnectionException('Failed to connect or create a socket: ' . $err_str);
        }

        $this->socket = $socket;
        $this->applyStreamTimeout();
    }

    protected function applyStreamTimeout(): void
    {
        $seconds = (int)floor($this->timeout);
        $microseconds = (int)round(($this->timeout - $seconds) * 1_000_000);

        stream_set_timeout($this->socket, $seconds, $microseconds);
    }

    /**
     * @param array $data
     * @return array
     */
    protected function encoding(array $data): array
    {
        return (array)mb_convert_encoding($data, $this->encoding);
    }
}
