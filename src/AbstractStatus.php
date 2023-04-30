<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki <kubuspl@onet.eu>
 * @copyright
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevLancer\MinecraftStatus;

use DevLancer\MinecraftStatus\Exception\Exception;
use InvalidArgumentException;

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
     * @var string[]
     */
    protected array $info = [];

    /**
     * @var string[]
     */
    protected array $players = [];

    /**
     * @var string|null
     */
    protected string $encoding = 'UTF-8';

    /**
     * QueryException constructor.
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @param bool $resolveSRV
     * @throws InvalidArgumentException
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
     * @throws Exception
     */
    protected function _connect(string $host, int $port)
    {
        $socket = @fsockopen($host, $port, $err_no, $err_str, $this->timeout);

        if($err_no || !is_resource($socket))
            throw new Exception( 'Could not create socket: ' . $err_str );

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->isConnected())
            $this->disconnect();
    }

    protected function disconnect()
    {
        fclose($this->socket);
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
     * @throws Exception
     */
    public function getPlayers(): array
    {
        if (!$this->isConnected())
            throw new Exception('The connection has not been established.');

        return $this->players;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getInfo(): array
    {
        if (!$this->isConnected())
            throw new Exception('The connection has not been established.');

        return $this->info;
    }

    /**
     * @return string|null
     */
    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * @param string|null $encoding
     */
    public function setEncoding(string $encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * @inheritDoc
     */
    public function setTimeout(int $timeout): void
    {
        if ($timeout <= 0)
            throw new InvalidArgumentException("The timeout must be a positive integer.");

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
}