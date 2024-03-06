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

abstract class AbstractStatus implements StatusInterface
{
    use ResolveSRVTrait;

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

    /**
     * @var int
     */
    protected int $timeout;

    /**
     * @var bool
     */
    protected bool $resolveSRV;

    /**
     * @var array<string, mixed>
     */
    protected array $info = [];

    /**
     * @var string
     */
    protected string $encoding = 'UTF-8';

    /**
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @param bool $resolveSRV
     * @throws \InvalidArgumentException The $timeout must be a positive integer
     */
    public function __construct(string $host, int $port = 25565, int $timeout = 3, bool $resolveSRV = true)
    {
        $this->resolveSRV = $resolveSRV;

        if ($this->resolveSRV) {
            $resolve = $this->resolveSRV($host);
            $host = ($resolve['host'] != null)? $resolve['host'] : $host;
            $port = ($resolve['port'] != null)? (int) $resolve['port'] : $port;
        }

        $this->host = $host;
        $this->port = $port;

        $this->setTimeout($timeout);
    }

    /**
     * @throws ConnectionException Thrown when failed to connect to resource
     */
    protected function _connect(string $host, int $port): void
    {
        $socket = @\fsockopen($host, $port, $err_no, $err_str, (float) $this->timeout);

        if($err_no || !\is_resource($socket))
            throw new ConnectionException( 'Failed to connect or create a socket: ' . $err_str );

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);
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
            if(fclose($this->socket))
                $this->socket = null;
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
        if (!$this->isConnected())
            throw new NotConnectedException('The connection has not been established.');

        return $this->info;
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
     * @inheritDoc
     * @throws \InvalidArgumentException The timeout must be a positive integer.
     */
    public function setTimeout(int $timeout): void
    {
        if ($timeout <= 0)
            throw new \InvalidArgumentException("The timeout must be a positive integer.");

        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function encoding(array $data): array
    {
        return (array) \mb_convert_encoding($data, $this->encoding);
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
}